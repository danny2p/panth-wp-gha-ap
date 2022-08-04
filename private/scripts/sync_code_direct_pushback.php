<?php

/*
*
* helper function to load token for github action 
* from private file system
*
* IMPORTANT. This script expects that you have used the 
* terminus secrets plugin to set secrets as outined below 
*
* To store and fetch we will use the terminus secrets plugin
* You’ll need your site id or machine name handy
* terminus secrets:set {site_id}.dev token ghp_xxxxxxxxxxxxxxxx
* terminus secrets:set {site_id}.dev git_remote https://github.com/{owner_name}/{your-repository}.git
* (Note this is the https path.)


* SFTP to dev server, navigate to files directory.
* Create /files/private/.build-secrets directory
* Enter your github token into tokens.json and place there
* ie: /files/private/.build-secrets/tokens.json
* Contents of tokens.json should be as follows:
* {"token":"ghp_xxxxxxxxxxxxxxxxxxxxxxxxx"}
*
*/

function load_git_secrets($gitSecretsFile) {
  if (!file_exists($gitSecretsFile)) {
    print "Could not find $gitSecretsFile\n";
    return [];
  }
  $gitSecretsContents = file_get_contents($gitSecretsFile);
  if (empty($gitSecretsContents)) {
    print "GitHub secrets file is empty\n";
    return [];
  }
  $gitSecrets = json_decode($gitSecretsContents, true);
  if (empty($gitSecrets)) {
    print "No data in Git secrets\n";
  }
  return $gitSecrets;
}

/*
*  We only want this firing on Dev for this case (ie: when code is merged to master)
*/ 

if (!isset($_ENV['PANTHEON_ENVIRONMENT']) || $_ENV['PANTHEON_ENVIRONMENT'] != "dev" ) {
    return;
}

$bindingDir = $_SERVER['HOME'];
$fullRepository = realpath("$bindingDir/code");
$privateFiles = realpath("$bindingDir/files/private");
$gitSecretsFile = "$privateFiles/secrets.json";
$gitSecrets = load_git_secrets($gitSecretsFile);
$git_token = $gitSecrets['token'];
$git_remote = $gitSecrets['remote'];
$auth_string = "$git_token@github.com";

// since we only asked for the https path and token we need to rebuild the url

$path_last = strrchr($git_remote, "/");
$git_owner = str_replace("https://github.com/", "", str_replace($path_last, "", $git_remote));
$git_remote_auth = "https://" . $git_owner . ":" . $auth_string . "/" . $git_owner . $path_last;

if (empty($git_token)) {
    $message = "Unable to load Git token from secrets file \n";
    print $message;
    return;
}

/*
*
* Let's check for changes in Github
*
* Since Pantheon is really authoritative, as we're running the code, we'll try to automatically
* push back to Github master.  In most cases this should be safe if commits to Github master
* branch are always being pushed to Pantheon.
*
*/

// latest local commit
$local = exec("git rev-parse @");
// latest github commit
$remote = exec('git ls-remote '.$git_remote_auth.' | head -1 | sed "s/HEAD//"');
// check if Pantheon's latest commit to master is a descendent of latest commit in github - if not we probably don't want to push
$is_remote_ancestor = exec("git merge-base --is-ancestor $remote master");
$ancestor_output = $is_remote_ancestor ? 'Github HEAD is not ancestor' : 'Github HEAD is ancestor';

// some debugging for workflow logs 
print "Local: $local \n";
print "Remote: $remote \n";
print "$ancestor_output \n";

if ($local == $remote) {
    // in many cases CI, or user pushed to Pantheon, so Pantheon will already be up to date with Github
    print "Up-to-date.";
    return;
} elseif ($is_remote_ancestor == 0) {
    // in the case of Autopilot or dashboard one-click update, Pantheon will have new commits.  Github head should be ancestor
    exec("git push $git_remote_auth master");
    print "\n Pushed to Github.";
} else {
    print "Pantheon and Github have diverged'.";
    // TODO - slack notification or other to notify user they may need to manually reconcile
    return;
}
