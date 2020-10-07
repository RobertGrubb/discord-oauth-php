<?php

  require __DIR__ . '/Discord.php';

  $discord = new Discord([
    'clientId' => 'XXXXX',
    'clientSecret' => 'XXXXXX'
  ]);

  $userData = $discord->login();

  print_r($userData);
?>
