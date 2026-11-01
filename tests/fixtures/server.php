<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

// Router for PHP's built-in web server, used by tests/HttpRequestTest.php.

$path = \parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);

switch ($path)
{
    case '/redirect':
        \header('Location: /echo?redirected=1', true, 302);
        return true;

    case '/slow':
        \sleep(5);
        echo 'too late';
        return true;

    case '/no-content-type':
        \header('Content-Type:');
        echo 'no type';
        return true;

    case '/echo':
    default:
        \header('Content-Type: application/json; charset=utf-8');
        echo \json_encode([
            'method' => $_SERVER['REQUEST_METHOD'],
            'query' => $_GET,
            'headers' => \getallheaders(),
            'body' => \file_get_contents('php://input'),
        ]);
        return true;
}
