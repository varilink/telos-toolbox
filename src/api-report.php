<?php
# tick use required for signal handler function

# signal handler function
function sig_handler($signo)
{
  switch ($signo) {
    case SIGINT:
      exit;
      break;
  }
}

pcntl_signal(SIGINT, 'sig_handler');

require '/usr/local/lib/autoload.php';

use Varilink\Telos\Chain;

$chain=new Chain($argv[1]);

print("\"Producer\",\"API Endpoint\",\"Version\",\"Block One\",\"Error\"\n");

foreach ($chain->producers as $producer) {

  if ( !$producer['is_active'] ) { continue; }

  /*----------------------------------------------------------------------------
  1. Check that the producer record on chain provides a valid, website URL
  ----------------------------------------------------------------------------*/
  if (
    !$producer['url'] || !filter_var($producer['url'], FILTER_VALIDATE_URL)
  ) {
    printf(
      "\"%s\",,,,\"No valid producer URL found on chain\"\n",
      $producer['owner']
    );
    continue;
  }
  /*----------------------------------------------------------------------------
  2. Retrieve /chains.json file from the producer's website
  ----------------------------------------------------------------------------*/
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
  curl_setopt($ch, CURLOPT_URL, $producer['url'] . '/chains.json');
  $body = curl_exec($ch);
  $info = curl_getinfo($ch);
  if (curl_errno($ch)) {
    printf(
      "\"%s\",,,,\"%s for %s\"\n",
      $producer['owner'],
      curl_error($ch),
      $info['url']
    );
    curl_close($ch);
    continue;
  }
  if ($info['http_code'] != 200) {
    printf(
      "\"%s\",,,,\"HTTP code %s for %s\"\n",
      $producer['owner'],
      $info['http_code'],
      $info['url']
    );
    curl_close($ch);
    continue;
  }
  /*----------------------------------------------------------------------------
  3. Check there is an entry in chains within /chains.json for our chain
  ----------------------------------------------------------------------------*/
  $data = json_decode($body, TRUE);
  if (
    # json_deocde was not able to parse the contents of chains.json
    is_null($data)
    # OR we couldn't find a 'chains' key within chains.json
    || !array_key_exists('chains', $data)
    # OR we couldn't find an entry for our desired chain id in chains.json
    || !array_key_exists($chain->chain_id, $data['chains'])
  ) {
    printf(
      "\"%s\",,,,\"No entry for target chain in %s\"\n",
      $producer['owner'],
      $info['url']
    );
    curl_close($ch);
    continue ;
  }
  /*----------------------------------------------------------------------------
  4. Retrieve indicated /bp.json (or other address given) file
  ----------------------------------------------------------------------------*/
  curl_setopt(
    $ch, CURLOPT_URL, $producer['url'] . $data['chains'][$chain->chain_id]
  );
  $body = curl_exec($ch);
  $info = curl_getinfo($ch);
  if (curl_errno($ch)) {
    printf(
      "\"%s\",,,,\"%s for %s\"\n",
      $producer['owner'],
      curl_error($ch),
      $info['url']
    );
    curl_close($ch);
    continue;
  }
  if ($info['http_code'] != 200) {
    printf(
      "\"%s\",,,,\"HTTP code %s for %s\"\n",
      $producer['owner'],
      $info['http_code'],
      $info['url']
    );
    curl_close($ch);
    continue;
  }
  curl_close($ch);
  /*----------------------------------------------------------------------------
  5. Retrieve valid API service addresses from the bp.json
  ----------------------------------------------------------------------------*/
  $data = json_decode($body, TRUE);
  $api_endpoints = [];
  if (
    # json_deocde was able to parse the contents of bp.json
    !is_null($data)
    # AND we found a nodes key within bp.json
    && array_key_exists('nodes',$data)
  ) {
    foreach ($data['nodes'] as $node) {
      if (array_key_exists('api_endpoint',$node)
      && $node['api_endpoint']
      && preg_match('/^(http|https)\:\/\/(.+?)(?:\:(\d+))?$/',
      $node['api_endpoint'], $matches)
      ) {
        $protocol = $matches[1];
        $host = $matches[2];
        if (count($matches) === 4) {
          $port = intval($matches[3]);
          if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
          && $port >= 0 && $port <= 65535) {
            $api_endpoints[] = "$protocol://$host:$port";
          }
        } elseif (
          filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
          $api_endpoints[] = "$protocol://$host";
        }
      }
    }
  }
  if ( !$api_endpoints ) {
    printf(
      "\"%s\",,,,\"No valid API endpoints found in %s\"\n" ,
      $producer['owner'],
      $info['url']
    );
    continue;
  }
  foreach ( $api_endpoints as $api_endpoint ) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    /*--------------------------------------------------------------------------
    6. Query the get_info API
    --------------------------------------------------------------------------*/
    curl_setopt( $ch, CURLOPT_URL, "$api_endpoint/v1/chain/get_info");
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    if (curl_errno($ch)) {
      printf(
        "\"%s\",,,,\"%s for %s\"\n",
        $producer['owner'],
        curl_error($ch),
        $info['url']
      );
      curl_close($ch);
      continue;
    }
    if ($info['http_code'] != 200) {
      printf(
        "\"%s\",,,,\"HTTP code %s for %s\"\n",
        $producer['owner'],
        $info['http_code'],
        $info['url']
      );
      curl_close($ch);
      continue;
    } else {
      $data = json_decode($body, TRUE);
      if (isset($data) && array_key_exists('server_version_string', $data)) {
        printf(
          "\"%s\",\"%s\",\"%s\",",
          $producer['owner'],
          $api_endpoint,
          $data['server_version_string']
        );
      }
    }
    /*--------------------------------------------------------------------------
    7. See if block one can be retrieved via the API node
    --------------------------------------------------------------------------*/
    curl_setopt( $ch, CURLOPT_URL, "$api_endpoint/v1/chain/get_block");
    curl_setopt(
      $ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ]
    );
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt(
      $ch, CURLOPT_POSTFIELDS, json_encode([ 'block_num_or_id' => 1 ])
    );
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    if (curl_errno($ch)) {
      printf(
        ",\"%s\"\n",
        curl_error($ch), $info['url']
      );
      curl_close($ch);
      continue;
    }
    if ( $info['http_code'] === 200 ) {
      $data = json_decode($body, TRUE);
      if (isset($data) && array_key_exists('id', $data)) {
        print("\"Found\",\n");
      } else {
        print("\"Not Found\",\n");
      }
    } elseif ( $info['http_code'] === 400 ) {
      print("\"Not Found\",\n");
    } else {
      printf(
        ",\"HTTP code %u for %s\"\n",
        $info['http_code'], $info['url']
      );
    }
    curl_close($ch);
  } # end of API Endpoint
} # end of Producer
