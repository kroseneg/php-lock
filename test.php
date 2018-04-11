<?php
require ("src/Lock.php");


#$lock = Lock::factory("OLD");
$lock = Lock::factory("Dreadlock");


#$lock->debug = true;

$arr = array(
	"bla::fasel",
	"ABCD",
	"test1",
	"test2",
	"test3",
	"test4",
);

shuffle($arr);

print "Locking... ";
foreach ($arr as $key) {
	print "$key ";
	$lock->lock($key);
	sleep(1);
}
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
