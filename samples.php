<?php

include('ghclient.php');

// defined in defines.inc
global $APIURL;
global $APIKEY;

function showRateLimitInfo($ghc)
{
  echo "Rate limit: {$ghc->getRateLimit()}\n";
  echo "Rate limit remaining: {$ghc->getRateLimitRemaining()}\n";
  echo "Rate limit resets at: {$ghc->getRateLimitResetTime()}\n";
}

$client = new ghclient($APIKEY, null);
$client->setDebug(true);

// show who I am and my details
$url = $APIURL . '/user';
$user_data = $client->request($url);
if (empty($user_data))
{
  echo "Error getting user data; can't proceed further!\n";
  exit(1);
}

echo json_encode($user_data, JSON_PRETTY_PRINT) . "\n";
showRateLimitInfo($client);

// show one repo
/*
$url = $user_data['repos_url'];
//$client->setPageSize(1);
//$client->setPage();
$repos = $client->request($url);

echo json_encode($repos, JSON_PRETTY_PRINT) . "\n";
showRateLimitInfo($client);
*/

// create a new file
/*
$url = $APIURL . '/repos/mromaine/github-api-play/contents/testfile.txt';
$content = base64_encode('Nothing to see here; move along.');
$data = array('message' => 'scripted file creation test', 'committer' => array('name' => 'matt romaine', 'email' => 'mromaine@gmail.com'), 'content' => $content);
$retval = $client->request($url, 'PUT', $data, 201);

echo json_encode($retval, JSON_PRETTY_PRINT) . "\n";
showRateLimitInfo($client);
*/

// show events for this repository
$url = $APIURL . '/repos/mromaine/github-api-play/events';
$retval = $client->request($url);

echo json_encode($retval, JSON_PRETTY_PRINT) . "\n";
showRateLimitInfo($client);

