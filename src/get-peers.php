<?php

require '/usr/local/lib/autoload.php';

use Varilink\Telos\Chain;

$chain=new Chain($argv[1]);

// Output header lines for p2p-peer-address list (nodeos config format)
printf("# p2p-peer-address list generated at %s\n", date('H:i:s \o\n d-M-y'));
printf("# Chain id=%s\n", $chain->chain_id);

// Output header lines for error report (CSV format)
fwrite(STDERR, "\"Producer\",\"Error\"\n");

foreach ($chain->producers as $producer) {

  /*----------------------------------------------------------------------------
  1. Check that the producer record on chain provides a valid, website URL
  ----------------------------------------------------------------------------*/
  if (
    !$producer['url'] || !filter_var($producer['url'], FILTER_VALIDATE_URL)
  ) {
    fwrite(STDERR, sprintf(
      "\"%s\",\"No valid producer URL found on chain\"\n",
      $producer['owner']
    ));
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
    fwrite(STDERR, sprintf(
      "\"%s\",\"%s for %s\"\n",
      $producer['owner'], curl_error($ch), $info['url']
    ));
    curl_close($ch);
    continue;
  }
  if ($info['http_code'] != 200) {
    fwrite(STDERR, sprintf(
      "\"%s\",\"HTTP code %s for %s\"\n",
      $producer['owner'], $info['http_code'], $info['url']
    ));
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
    fwrite(STDERR, sprintf(
      "\"%s\",\"No entry for target chain in %s\"\n",
      $producer['owner'], $info['url']
    ));
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
    fwrite(STDERR, sprintf(
      "\"%s\",\"%s for %s\n", $producer['owner'], curl_error($ch), $info['url']
    ));
    curl_close($ch);
    continue;
  }
  if ($info['http_code'] != 200) {
    fwrite(STDERR, sprintf(
      "\"%s\",\"HTTP code %s for %s\"\n",
      $producer['owner'], $info['http_code'], $info['url']
    ));
    curl_close($ch);
    continue;
  }
  /*----------------------------------------------------------------------------
  5. Retrieve valid p2p_endpoint addresses from the bp.json
  ----------------------------------------------------------------------------*/
  $data = json_decode($body, TRUE);
  $p2p_endpoints = [];
  if (
    # json_deocde was able to parse the contents of bp.json
    !is_null($data)
    # AND we found a nodes key within bp.json
    && array_key_exists('nodes',$data)
  ) {
    foreach ( $data['nodes'] as $node ) {
      if ( array_key_exists('p2p_endpoint',$node)
        && $node['p2p_endpoint']
        && preg_match('/^(.+):(\d+)$/', $node['p2p_endpoint'], $matches)
      ) {
        $host = $matches[1];
        $port = intval($matches[2]);
        if ( filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
          && $port >= 0 && $port <= 65535 )
        {
          $p2p_endpoints[] = ['host'=>$host, 'port'=>$port];
        }
      }
    }
  }
  if ( !$p2p_endpoints ) {
    fwrite(STDERR, sprintf(
      "\"%s\",\"No valid p2p endpoints found in %s\"\n" ,
      $producer['owner'], $info['url']
    ));
    curl_close($ch);
    continue;
  }
  /*----------------------------------------------------------------------------
  6. Test if we can get a Telnet connection to the p2p_endpoint(s)
  ----------------------------------------------------------------------------*/
  $valid_p2p_endpoints = [];
  foreach ($p2p_endpoints as $p2p_endpoint) {
    # Note suppression of fsockopen error messages to STDERR using @
    # We will report these in our own way
    $socket = @fsockopen(
      $p2p_endpoint['host'], $p2p_endpoint['port'], $errno, $errstr, 2
    );
    if ( $socket ) {
      # Socket established, wait a second to see if the connection remains open
      sleep(1);
      if ( feof($socket) ) {
        fwrite(STDERR, sprintf(
          "\"%s\", \"Connection closed for %s:%u\"\n",
          $producer['owner'], $p2p_endpoint['host'], $p2p_endpoint['port']
        ));
      } else {
        $valid_p2p_endpoints[] = $p2p_endpoint;
      }
      fclose($socket);
    } else {
      fwrite(STDERR, sprintf(
        "\"%s\", \"%s for %s:%u\"\n",
        $producer['owner'], $errstr, $p2p_endpoint['host'],
        $p2p_endpoint['port']
      ));
    }
  }
  /*----------------------------------------------------------------------------
  7. If we have valid P2P endpoints for this producer then write them out
  ----------------------------------------------------------------------------*/
  if ( $valid_p2p_endpoints ) {
    printf("# %s\n", $producer['owner']);
    foreach ($valid_p2p_endpoints as $p2p_endpoint) {
      printf(
        "p2p-peer-address=%s:%u\n", $p2p_endpoint['host'], $p2p_endpoint['port']
      );
    }
  }

  curl_close($ch);

}
