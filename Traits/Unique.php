<?php

declare(strict_types=1);

namespace Inspira\Collection\Traits;

use Inspira\Collection\Exceptions\ColumnNotFoundException;

/**
 * The Unique trait provides functionality to filter unique items in a collection
 * based on a specified column or key, including support for nested keys using dots.
 *
 * @property $items
 * @property $isMutable
 */
trait Unique
{
	/**
	 * Filter the collection to contain only unique items based on the specified column.
	 *
	 * @param string|null $column The column or key to be used for uniqueness. If null, the entire item is considered for uniqueness.
	 * @param bool $strict Whether to perform a strict comparison when checking for uniqueness. Default is false (non-strict comparison).
	 * @return static Returns a new collection or mutates the existing one based on isMutable property.
	 * @throws ColumnNotFoundException
	 */
	public function unique(string $column = null, bool $strict = false): static
	{
		$items = [];
		$tracker = [];

		foreach ($this->items as $item) {
			$value = stringable($item) || is_null($column)
				? $item
				: $this->getValueByDottedKey($item, $column);

			if (!in_array($value, $tracker, $strict)) {
				$items[] = $item;
				$tracker[] = $value;
			}
		}

		if ($this->isMutable) {
			$this->items = $items;

			return $this;
		}

		$collection = clone $this;
		$collection->items = $items;

		return $collection;
	}

	/**
	 * Retrieve a nested value from an array or object based on a dotted key.
	 *
	 * @param mixed $array The array or object from which to retrieve the value.
	 * @param string $key The dotted key to identify the nested value.
	 * @return mixed The retrieved nested value.
	 * @throws ColumnNotFoundException If the specified key is not found in the collection item.
	 */
	protected function getValueByDottedKey(mixed $array, string $key): mixed
	{
		$keys = explode('.', $key);
		$value = $array;

		foreach ($keys as $key) {
			if (is_array($value) && isset($value[$key])) {
				$value = $value[$key];
			} elseif (is_object($value) && property_exists($value, $key)) {
				$value = $value->$key;
			} else {
				throw new ColumnNotFoundException("Column [$key] is not found in the collection item.");
			}
		}

		return $value;
	}
}
