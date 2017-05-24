<?php

function fib($n = null)
{
    if ($n > 100) {
        die('Number must <= 100!');
    }
    for ($i = 0; $i <= $n; $i ++) {
        echo $this->getNum($i) . ' ';
    }
}

function getNum($n)
{
    $key = 'com.tanteng.me.test.foo';
    $value = Redis::hget($key, $n);
    if ($value) {return $value;}
    if ($n == 0) $result = 1;
    if ($n == 1) $result = 1;
    if ($n > 1) {
        $result = $this->getNum($n - 2) + $this->getNum($n - 1);
    }
    Redis::hset($key, $n, $result);
    Redis::expire($key, 1800);
    return $result;
}
?>
