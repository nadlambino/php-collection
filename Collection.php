<?php

declare(strict_types=1);

namespace Inspira\Collection;

use ArrayObject;
use Inspira\Collection\Enums\Filter;
use Inspira\Collection\Exceptions\CollectionItemNotFoundException;
use Inspira\Collection\Exceptions\CollectionNotAccessibleException;
use Inspira\Contracts\Arrayable;
use OutOfBoundsException;
use ReturnTypeWillChange;
use Traversable;

/**
 * Class Collection
 *
 * Represents a collection of items with various utility methods for manipulation and filtering.
 *
 * @package Inspira\Collection
 */
class Collection extends ArrayObject implements Arrayable
{
	/**
	 * Collection constructor.
	 *
	 * @param array|object $array The initial data for the collection.
	 * @param int $flags Flags controlling the behavior of the ArrayObject.
	 * @param string $iteratorClass The name of the iterator class to use.
	 */
	public function __construct(object|array $array = [], int $flags = 0, string $iteratorClass = "ArrayIterator")
	{
		parent::__construct($array, $flags, $iteratorClass);
	}

	/**
	 * Create a new collection instance.
	 *
	 * @param array|object $data The initial data for the collection.
	 *
	 * @return static The new collection instance.
	 */
	public static function make(array|object $data): static
	{
		return new static($data);
	}

	/**
	 * Get the first item in the collection.
	 *
	 * @return mixed The first item.
	 */
	public function first(): mixed
	{
		return $this->index(0, false);
	}

	/**
	 * Get the last item in the collection.
	 *
	 * @return mixed The last item.
	 */
	public function last(): mixed
	{
		return $this->index($this->getIterator()->count() - 1, false);
	}

	/**
	 * Get an item at a specific index.
	 *
	 * @param int $index The index of the item.
	 * @param bool $strict Whether to throw an exception if the index is out of bounds.
	 *
	 * @return mixed|null The item at the specified index.
	 * @throws OutOfBoundsException If the index is out of bounds and strict mode is enabled.
	 */
	public function index(int $index, bool $strict = true): mixed
	{
		$items = $this->getIterator();
		$count = $items->count();

		if ($count === 0 && $strict === true) {
			throw new OutOfBoundsException("Can not access an item of an empty collection");
		}

		if ($count === 0) {
			return null;
		}

		$items->seek($index);
		$item = $items->current();

		return $this->isTraversable($item) ? new static($item) : $item;
	}

	/**
	 * Get the values of a given column.
	 *
	 * @param string $name The name of the column.
	 * @param ?string $key The name of the key column.
	 *
	 * @return static A new collection with the values of the specified column.
	 */
	public function column(string $name, string $key = null): static
	{
		return new static(array_column($this->toArray(), $name, $key));
	}

	/**
	 * Chunk the collection into smaller chunks.
	 *
	 * @param int $length The size of each chunk.
	 * @param bool $preserveKeys Whether to preserve the keys of the original collection.
	 *
	 * @return static A new collection with the chunked data.
	 */
	public function chunk(int $length, bool $preserveKeys = false): static
	{
		return new static(array_chunk($this->toArray(), $length, $preserveKeys));
	}

	/**
	 * Add keys to the collection using the given keys.
	 *
	 * @param array|Traversable $keys The keys to use.
	 *
	 * @return static A new collection with the specified keys.
	 */
	public function withKeys(array|Traversable $keys): static
	{
		$keys = is_array($keys) ? $keys : iterator_to_array($keys);

		return new static(array_combine($keys, $this->toArray()));
	}

	/**
	 * Determine if the collection has a given item.
	 *
	 * @param array|Traversable $item The item to search for.
	 *
	 * @return bool True if the collection has the specified item, false otherwise.
	 */
	public function has(array|Traversable $item): bool
	{
		$item = is_array($item) ? $item : iterator_to_array($item);

		return in_array($item, $this->toArray());
	}

	/**
	 * Get the keys of the collection items.
	 *
	 * @return static A new collection with the keys of the original collection.
	 */
	public function keys(): static
	{
		return new static(array_keys($this->toArray()));
	}

	/**
	 * Get the values of the collection items.
	 *
	 * @return static A new collection with the values of the original collection.
	 */
	public function values(): static
	{
		return new static(array_values($this->toArray()));
	}

	/**
	 * Append a value to the end of the collection.
	 *
	 * @param mixed $value The value to append.
	 *
	 * @return static The modified collection.
	 */
	#[ReturnTypeWillChange]
	public function append(mixed $value): static
	{
		parent::append($value);

		return $this;
	}

	/**
	 * Prepend a value to the beginning of the collection.
	 *
	 * @param mixed $value The value to prepend.
	 *
	 * @return static A new collection with the prepended value.
	 */
	public function prepend(mixed $value): static
	{
		$items = $this->toArray();
		array_unshift($items, $value);

		return new static($items);
	}

	/**
	 * Remove an item from the collection by key.
	 *
	 * @param int|string $key The key of the item to remove.
	 *
	 * @return static A new collection with the specified item removed.
	 */
	public function unset(int|string $key): static
	{
		$items = $this->toArray();
		unset($items[$key]);

		return new static($items);
	}

	/**
	 * Filter the collection items based on the given filters.
	 *
	 * @param mixed $filters The filters to apply.
	 * @param bool $strict Whether to perform a strict comparison.
	 *
	 * @return static A new collection with the filtered items.
	 */
	public function where(mixed $filters, bool $strict = true): static
	{
		return $this->filter($filters, $strict, Filter::WHERE);
	}

	/**
	 * Filter the collection items based on the given filters using a "like" comparison.
	 *
	 * @param mixed $filters The filters to apply.
	 * @param bool $strict Whether to perform a strict comparison.
	 *
	 * @return static A new collection with the filtered items.
	 */
	public function like(mixed $filters, bool $strict = false): static
	{
		return $this->filter($filters, $strict, Filter::LIKE);
	}

	/**
	 * Convert the collection to a plain PHP array.
	 *
	 * @return array The plain PHP array representation of the collection.
	 */
	public function toArray(): array
	{
		return iterator_to_array($this);
	}

	/**
	 * Dynamically retrieve the value of an item in the collection.
	 *
	 * @param string $name The name of the item.
	 *
	 * @return mixed The value of the specified item.
	 * @throws CollectionItemNotFoundException If the item is not found in the collection.
	 * @throws CollectionNotAccessibleException If the collection is not accessible.
	 */
	public function __get(string $name): mixed
	{
		if (!$this->isTraversable($this)) {
			throw new CollectionNotAccessibleException('Collection item is not an instance of ArrayObject');
		}

		if (!isset($this[$name])) {
			throw new CollectionItemNotFoundException(sprintf('Property `%s` does not exist in the collection', $name));
		}

		return $this[$name];
	}

	/**
	 * Check if a value is traversable (array, object, or Traversable).
	 *
	 * @param mixed $data The value to check.
	 *
	 * @return bool True if the value is traversable, false otherwise.
	 */
	private function isTraversable(mixed $data): bool
	{
		return is_array($data) || is_object($data) || $data instanceof Traversable;
	}

	/**
	 * Apply filtering to the collection based on the given filters.
	 *
	 * @param mixed $filters The filters to apply.
	 * @param bool $strict Whether to perform a strict comparison.
	 * @param Filter $type The type of filtering to apply.
	 *
	 * @return static A new collection with the filtered items.
	 */
	private function filter(mixed $filters, bool $strict, Filter $type): static
	{
		$filters = $filters instanceof Traversable ? iterator_to_array($filters) : $filters;

		$result = array_filter($this->toArray(), function ($item) use ($filters, $strict, $type) {
			// Check if collection item is a stringable value and the filter is not an array
			if (stringable($item) && !is_array($filters)) {
				$itemValue = $strict === false ? strtolower((string)$item) : $item;
				$filterValue = $strict === false ? strtolower((string)$filters) : $filters;

				return match (true) {
					$type === Filter::LIKE => str_contains($itemValue, $filterValue),
					$strict === true => $itemValue === $filterValue,
					$strict === false => $itemValue == $filterValue,
					default => false
				};
			}

			// Check if collection item is not a stringable and filters is not an array
			// Or collection item is not an array
			// Or filters is not an associative array
			if (
				(!is_array($filters) && !stringable($item)) ||
				!is_array($item) ||
				is_int((new static(array_keys($filters)))->first())
			) {
				return false;
			}

			foreach ($filters as $key => $value) {
				if (!isset($item[$key])) {
					return false;
				}

				$itemValue = $strict === false ? strtolower((string)$item[$key]) : $item[$key];
				$filterValue = $strict === false ? strtolower((string)$value) : $value;

				if ($type === Filter::LIKE && !str_contains($itemValue, $filterValue)) {
					return false;
				}

				if ($type === Filter::WHERE) {
					return match (true) {
						$strict === true && $itemValue !== $filterValue,
						$strict === false && $itemValue != $filterValue => false,
						default => true
					};
				}
			}

			return true;
		});

		return new static($result);
	}
}
