<?php

/*
*
* helper function to load token for github action 
* from private file system
*
* IMPORTANT. This script expects that you have used the 
* terminus secrets plugin to set secrets as outined below 
*
* if not using terminus secrets, manually create /files/private/secrets.json
*
* To store and fetch we will use the terminus secrets plugin
* You’ll need your site id or machine name handy
* terminus secrets:set {site_id}.dev token ghp_xxxxxxxxxxxxxxxx
* terminus secrets:set {site_id}.dev git_remote https://github.com/{owner_name}/{your-repository}.git
* (Note this is the https path.)
*
*/

function load_git_secrets($git_secrets_file) {
  if (!file_exists($git_secrets_file)) {
    print "Could not find $git_secrets_file\n";
    return [];
  }
  $git_secrets_content = file_get_contents($git_secrets_file);
  if (empty($git_secrets_content)) {
    print "GitHub secrets file is empty\n";
    return [];
  }
  $git_secrets = json_decode($git_secrets_content, true);
  if (empty($git_secrets)) {
    print "No data in Git secrets\n";
  }
  return $git_secrets;
}

/*
*  If we only want this firing on Dev, or a specific branch (ie: when code is merged to master), use a check like below
*/ 

/*
if (!isset($_ENV['PANTHEON_ENVIRONMENT']) || $_ENV['PANTHEON_ENVIRONMENT'] != "dev" ) {
    return;
}
*/


/*
*
* Since Pantheon is really authoritative, in the sense that we're running the code, 
* we'll try to automatically push back to Github master.
* In most cases this should be safe if commits to Github master
* branch are always being pushed to Pantheon.
* extend this logic as necessary to fit your needs.
*
*/


$private_files = realpath($_SERVER['HOME']."/files/private");
$git_secrets_file = "$private_files/secrets.json";
$git_secrets = load_git_secrets($git_secrets_file);
$git_token = $git_secrets['token'];
$git_remote = $git_secrets['git_remote'];
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
* Since Pantheon is really authoritative, in the sense that we're running the code, 
* we'll try to automatically push back to Github master.
* In most cases this should be safe if commits to Github master
* branch are always being pushed to Pantheon.
* extend this logic as necessary to fit your needs.
*
*/

if ($_ENV['PANTHEON_ENVIRONMENT'] == "dev") {
  $git_branch = "master";
} else { #multidev case
  $git_branch = $_ENV['PANTHEON_ENVIRONMENT'];
}

exec("git pull $git_remote_auth");
exec("git push --set-upstream $git_remote_auth HEAD:$git_branch");
print "\n $git_remote_auth";
print "\n Pushed to remote repository.";