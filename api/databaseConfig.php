<?php

// require __DIR__ . '/../vendor/autoload.php';

// $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
// $dotenv->load();

/**
 * PostgreSQLに接続
 */
function databaseConnection(): PDO
{
    $deployConnection = "pgsql:host={$_ENV['PGHOST']};port=5432;user={$_ENV['PGUSER']};password={$_ENV['PGPASSWORD']};dbname={$_ENV['DBNAME']};";
    // $testConnection = "pgsql:host={$_ENV['PGHOST']};port=5432;dbname={$_ENV['DBNAME']};user={$_ENV['PGUSER']};password={$_ENV['PGPASSWORD']};options=endpoint={$_ENV['ENDPOINT']};sslmode=require";
    $dataBaseObject = new PDO($deployConnection);
    $dataBaseObject->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $dataBaseObject;
}