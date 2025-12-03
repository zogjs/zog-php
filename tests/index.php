<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Zog\Zog;

// ------------------------------
//  Configure directories
// ------------------------------
Zog::setViewDir(__DIR__ . '/views');
Zog::setStaticDir(__DIR__ . '/cache/static');
Zog::setCompiledDir(__DIR__ . '/cache/compiled');


$data = [
    'page_title' => 'My Products',

    'products' => [
        ['id' => 1, 'name' => 'JavaScript Course', 'model' => 'sx1', 'amount' => 0],
        ['id' => 1, 'name' => 'AI Course', 'model' => 'sx1', 'amount' => 230],
    ],
    'tags'=>['PHP', 'JavaScript', 'AI']
];


Zog::clearStatics();

echo Zog::hybrid('myview.php', 'test', function () use ($data) {

    return $data;
});
