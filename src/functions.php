<?php

use Inspira\Collection\Collection;
use Inspira\Collection\Contracts\CollectionInterface;
use Inspira\Collection\Enums\Type;

if (!function_exists('collection')) {
	function collection(CollectionInterface|array $items, mixed $type = Type::MIXED, bool $isLiteralType = false, bool $isMutable = false): CollectionInterface
	{
		return new Collection($items, $type, $isLiteralType, $isMutable);
	}
}
