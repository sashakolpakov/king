--TEST--
King MoE training network coordinates a distributed GPU worker fleet instead of centralized compute
--FILE--
<?php
require dirname(__DIR__, 2) . '/demo/userland/training-php/tests/moe-distributed-network-contract.php';
?>
--EXPECT--
moe distributed network contract ok
