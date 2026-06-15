<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

$jwtDir = dirname(__DIR__).'/var/jwt-test';
$jwtPrivateKey = $jwtDir.'/private.pem';
$jwtPublicKey = $jwtDir.'/public.pem';
$jwtPassphrase = 'test_passphrase';

if (!is_file($jwtPrivateKey) || !is_file($jwtPublicKey)) {
    if (!is_dir($jwtDir)) {
        mkdir($jwtDir, 0775, true);
    }

    $privateKey = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($privateKey, $privatePem, $jwtPassphrase);
    $publicPem = openssl_pkey_get_details($privateKey)['key'];

    file_put_contents($jwtPrivateKey, $privatePem);
    file_put_contents($jwtPublicKey, $publicPem);
}

$_SERVER['JWT_SECRET_KEY'] = $_ENV['JWT_SECRET_KEY'] = $jwtPrivateKey;
$_SERVER['JWT_PUBLIC_KEY'] = $_ENV['JWT_PUBLIC_KEY'] = $jwtPublicKey;
$_SERVER['JWT_PASSPHRASE'] = $_ENV['JWT_PASSPHRASE'] = $jwtPassphrase;
putenv('JWT_SECRET_KEY='.$jwtPrivateKey);
putenv('JWT_PUBLIC_KEY='.$jwtPublicKey);
putenv('JWT_PASSPHRASE='.$jwtPassphrase);

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
