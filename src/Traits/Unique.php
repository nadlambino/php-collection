<?php

declare(strict_types=1);

namespace Inspira\Collection\Traits;

use Closure;
use Inspira\Collection\Exceptions\ColumnNotFoundException;
use function Inspira\Utils\stringable;

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
	 * Filter the collection to contain only unique items based on a specified column or using a custom closure.
	 *
	 * @param Closure|string|null $column The column name or closure used for uniqueness comparison. Default is null.
	 * @param bool $strict Whether to perform a strict type check when comparing items. Default is false.
	 * @param bool $throwIfColumnNotFound Whether to throw an exception if the specified column is not found. Default is true.
	 * @return static A new collection containing only unique items based on the specified column or closure.
	 * @throws ColumnNotFoundException If $throwIfColumnNotFound is true and the specified column is not found.
	 */
	public function unique(Closure|string $column = null, bool $strict = false, bool $throwIfColumnNotFound = true): static
	{
		$items = $this->getUniqueItems($column, $strict, $throwIfColumnNotFound);

		if ($this->isMutable) {
			$this->items = $items;

			return $this;
		}

		$collection = clone $this;
		$collection->items = $items;

		return $collection;
	}

	/**
	 * Filter the collection to contain only unique items based on a specified column or using a custom closure.
	 *
	 * @param Closure|string|null $column The column name or closure used for uniqueness comparison. Default is null.
	 * @param bool $strict Whether to perform a strict type check when comparing items. Default is false.
	 * @param bool $throwIfColumnNotFound Whether to throw an exception if the specified column is not found. Default is true.
	 * @return array An array of unique items.
	 * @throws ColumnNotFoundException If $throwIfColumnNotFound is true and the specified column is not found.
	 */
	protected function getUniqueItems(Closure|string|null $column, bool $strict, bool $throwIfColumnNotFound = true): array
	{
		if ($column instanceof Closure) {
			return $column($this->items);
		}

		$items = [];
		$tracker = [];

		foreach ($this->items as $item) {
			try {
				$value = stringable($item) || is_null($column)
					? $item
					: $this->getValueByDottedKey($item, $column);

				if (!in_array($value, $tracker, $strict)) {
					$items[] = $item;
					$tracker[] = $value;
				}
			} catch (ColumnNotFoundException $exception) {
				if ($throwIfColumnNotFound) {
					throw $exception;
				}

				$items[] = $item;
			}
		}

		return $items;
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
