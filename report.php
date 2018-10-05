<?php

include_once "config.php";

/**
 * A standard usage of curl
 * 
 * @param string $method {POST, GET}
 * @param string $url
 * @param array $data
 * @return type
 */
function call_api(string $method, string $url, array $data = array()) {
  $curl = curl_init();

  if ($method == 'POST') {
    curl_setopt($curl, CURLOPT_POST, true);
    // @TODO: check the type of sending params
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
  } else {
    $url = sprintf("%s?%s", $url, http_build_query($data));
  }

  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
  ));
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

  $result = curl_exec($curl);
  if (!$result) {
    die("Connection failure");
  }
  curl_close($curl);
  return $result;
}

/**
 * Returns the api token
 * @TODO: could cache the key like in a file
 * 
 * @return string
 */
function get_api_token() {
  global $config;
  $url = $config['key_url'];

  $post_parms = array(
    'client_id' => $config['client_id'],
    'email' => 'a@a.a',
    'name' => 'tester',
  );

  $json = call_api('POST', $url, $post_parms);
  $res = json_decode($json, true);

  if (!isset($res['data']['sl_token'])) {
    die($res);
  } else {
    echo "...token get\n";
  }
  return $res['data']['sl_token'];
}

function get_data_posts(int $month = 0) {
  global $config;
  $posts = array();
  $url = $config['posts_url'];
  $get_parms = array(
    'sl_token' => get_api_token()
  );


  echo "fetching data now..";

  for ($i = 1; $i < 11; $i++) {
    $get_parms['page'] = $i;
    $json = call_api('GET', $url, $get_parms);
    $res = json_decode($json, true);

    if (!isset($res['data']['posts'])) {
      die("invalid response");
    }

    $posts = array_merge($posts, filter_post_data_by_month($res['data']['posts'], $month));
    echo '.';
  }
  return $posts;
}

/**
 * A function that cache the response and remove the unnecessary key/value to
 * keep a better performance
 * @param array $posts
 * @param int $month
 * @return array
 */
function filter_post_data_by_month(array $posts, int $month = 0) {
  if (empty($posts)) {
    return $posts;
  }

  $results = array();
  $filtered = array();

  foreach ($posts as $post) {
    $created_month = date('m', strtotime($post['created_time']));
    if (!empty($month) && $created_month != $month) {
      continue;
    }
    $filtered['from_id'] = $post['from_id'];
    $filtered['created_time'] = $post['created_time'];

    // small optimization since the value is used for several times
    $filtered['post_length'] = strlen($post['message']);

    // @TODO: could be also saved in database such as MongoDB
    $results[] = $filtered;
  }

  return $results;
}

/**
 * @TODO: noted that if the object of "longest post" is requred, there might 
 * be more than one post
 * 
 * @param array $data
 * @return int
 */
function find_length_of_longest_post_by_char(array $data) {
  $winning_length = 0;

  foreach ($data as $post) {
    if ($post['post_length'] > $winning_length) {
      $winning_length = $post['post_length'];
    }
  }

  return $winning_length;
}

function find_average_length_per_post(array $data) {
  if (count($data) == 0) {
	  return 0;
  }
  
  $char_count = 0;
  foreach ($data as $post) {
    $char_count += $post['post_length'];
  }

  return round($char_count / count($data), 1);
}

function find_average_posts_per_user(array $data) {
  $user_count = 0;
  $user_pool = array();

  foreach ($data as $post) {
    if (!in_array($post['from_id'], $user_pool)) {
      $user_pool[] = $post['from_id'];
      $user_count += 1;
    }
  }

  return empty($user_count) ? 0 : round(count($data) / $user_count, 1);
}

function get_total_posts_per_week(array $data) {
  $posts_by_week = array();
  $counts_per_week = array();

  foreach ($data as $post) {
    $week = date('W', strtotime($post['created_time']));
    $posts_by_week[$week][] = $post;
  }
  ksort($posts_by_week);

  // @TODO: could be optimized if $posts_by_week does not need to be returned
  foreach ($posts_by_week as $week => $weekly_posts) {
    $counts_per_week[$week] = count($weekly_posts);
  }

  return $counts_per_week;
}

function run() {
  $ops = getopt("m:");
  if (!isset($ops['m'])) {
    die("Using -m to assign a valid month number\n");
  } else {
    $month = $ops['m'];
    if ($month < 1 || $month > 12) {
      die("Please give a valid month number from 1 to 12\n");
    }
  }

  echo "\n_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/\n";
  echo "Script starts\n";
  echo "_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/\n";
  $data = get_data_posts($month);
  echo "\n";

  echo "month: $month \n";
  echo "Total posts in this month found: " . count($data);
  echo "\n";

  echo "Average character length per post: ";
  echo find_average_length_per_post($data);
  echo "\n";

  echo "Longest post by character length: ";
  echo find_length_of_longest_post_by_char($data);
  echo "\n";

  $weekly_posts = get_total_posts_per_week($data);
  echo "Total posts split by week: \n";
  foreach ($weekly_posts as $week => $count) {
    echo " -week <$week>: $count posts \r\n";
  }
  echo "\n";

  echo "Average number of posts per user: ";
  echo find_average_posts_per_user($data);
  echo "\n";
}

run();
