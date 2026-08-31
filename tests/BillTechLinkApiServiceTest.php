<?php
// Standalone regression check (no framework): php tests/BillTechLinkApiServiceTest.php
// Truncation must cut by characters, never mid multi-byte UTF-8 char,
// otherwise toUtf8() re-detects the string as CP1250 and mangles it.

require __DIR__ . '/../lib/BillTechLinkApiService.php';

function call($method, $arg)
{
	$ref = new ReflectionMethod(BillTechLinkApiService::class, $method);
	$ref->setAccessible(true);
	return $ref->invoke(null, $arg);
}

// byte 105 would land mid "ą" (C4 85) with byte-based substr
$title = str_repeat('x', 104) . 'ąęść usługa';
$cut = call('getTitle', $title);
assert(mb_check_encoding($cut, 'UTF-8'));
assert(mb_strlen($cut, 'UTF-8') === 105);
assert(mb_substr($cut, -1, 1, 'UTF-8') === 'ą');

$name = str_repeat('x', 99) . 'ł';
$cut = call('getNameOrSurname', $name);
assert(mb_check_encoding($cut, 'UTF-8'));
assert($cut === $name);

$division = str_repeat('x', 34) . 'ż tail';
$cut = call('getRecipientName', $division);
assert(mb_check_encoding($cut, 'UTF-8'));
assert($cut === str_repeat('x', 34) . 'ż');

echo "OK\n";
