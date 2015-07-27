<?php
require ("Lock.php");


$lock = Lock::factory("Dreadlock");



print "Locking...\n";
$lock->lock("ABCD");
$lock->lock("test1");
$lock->lock("test2");
$lock->lock("test3");
print "Locked.\n";
sleep(3);
print "Un-Locking...\n";
$lock->unlock("test");
print "Unlocked.\n";
sleep(3);

test:
print "Locking...\n";
$lock->lock("test");
print "Locked.\n";
sleep(3);
print "Un-Locking.\n";
$lock->unlock("test");
print "Unlocked.\n";
sleep(3);
?>
