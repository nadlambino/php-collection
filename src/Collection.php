<?php

declare(strict_types=1);

namespace Inspira\Collection;

use ArrayIterator;
use Closure;
use Inspira\Collection\Contracts\CollectionInterface;
use Inspira\Collection\Enums\Type;
use Inspira\Collection\Exceptions\ImmutableCollectionException;
use Inspira\Collection\Exceptions\InvalidLiteralTypeException;
use Inspira\Collection\Exceptions\InvalidTypeException;
use Inspira\Collection\Exceptions\ItemNotFoundException;
use Inspira\Collection\Traits\Unique;
use Inspira\Collection\Traits\Whereable;
use Inspira\Contracts\Arrayable;
use Traversable;

/**
 * Collection class represents a collection of items with specified types.
 *
 * @template Item The type of collection item.
 * @package Inspira\Collection
 */
class Collection implements CollectionInterface
{
	use Whereable, Unique;

	/**
	 * Constructor for Collection.
	 *
	 * @param array|Item[]|CollectionInterface<Item> $items The initial set of items for the collection.
	 * @param class-string<Item>|Item|Type|mixed $type The expected type of collection items.
	 * @param bool $isLiteralType Indicates whether the type is literal or not.
	 * @param bool $isMutable Indicates whether the collection is mutable or not.
	 */
	public function __construct(
		protected array|CollectionInterface $items = [],
		protected mixed                     $type = Type::MIXED,
		public readonly bool                $isLiteralType = false,
		public readonly bool                $isMutable = false
	)
	{
		$this->items = $items instanceof CollectionInterface ? $items->toArray() : $items;
		$this->validate();
	}

	/**
	 * Magic method to get the item from the collection or throw an exception if it does not exist.
	 *
	 * @param string $name The name of the collection item.
	 * @return ?Item
	 * @throws ItemNotFoundException
	 */
	public function __get(string $name): mixed
	{
		if (isset($this->items[$name])) {
			return $this->items[$name];
		}

		throw new ItemNotFoundException("Item [$name] does not exist in the collection.");
	}

	/**
	 * Magic method to set an item in the collection.
	 *
	 * @param string $name
	 * @param Item $value
	 * @return void
	 */
	public function __set(string $name, mixed $value): void
	{
		$this->validateItemType($value);

		$this->items[$name] = $value;
	}

	/**
	 * Serializes the collection to an array.
	 *
	 * @return array
	 */
	public function __serialize(): array
	{
		return $this->items;
	}

	/**
	 * Unserializes the collection from an array.
	 *
	 * @param array $data The data to unserialize.
	 */
	public function __unserialize(array $data): void
	{
		$this->items = $data;
	}

	/**
	 * Gets the type of the collection.
	 *
	 * @return mixed
	 */
	public function getType(): mixed
	{
		return $this->type instanceof Type ? $this->type->value : $this->type;
	}

	/**
	 * Gets the count of items in the collection.
	 *
	 * @return int
	 */
	public function count(): int
	{
		return count($this->items);
	}

	/**
	 * Converts the collection to an array.
	 *
	 * @return Item[]
	 */
	public function toArray(): array
	{
		return array_values(array_map(function($item) {
			return match (true) {
				$item instanceof Arrayable => $item->toArray(),
				is_iterable($item) => array_values(iterator_to_array($item)),
				default => $item
			};
		}, $this->items));
	}

	/**
	 * Checks if an offset exists in the collection.
	 *
	 * @param mixed $offset The offset to check.
	 * @return bool
	 */
	public function offsetExists(mixed $offset): bool
	{
		return isset($this->items[$offset]);
	}

	/**
	 * Gets the item at the specified offset.
	 *
	 * @param mixed $offset The offset to retrieve.
	 * @return Item
	 */
	public function offsetGet(mixed $offset): mixed
	{
		return $this->items[$offset] ?? null;
	}

	/**
	 * Sets the value at the specified offset.
	 * If offset is empty, push the value to items.
	 *
	 * @param mixed $offset The offset to set.
	 * @param Item $value The value to set.
	 */
	public function offsetSet(mixed $offset, mixed $value): void
	{
		$this->validateItemType($value);

		if ($this->isMutable === false) {
			throw new ImmutableCollectionException("Cannot set an item of an immutable collection.");
		}

		empty($offset) ? $this->items[] = $value : $this->items[$offset] = $value;
	}

	/**
	 * Unsets the item at the specified offset.
	 *
	 * @param mixed $offset The offset to unset.
	 */
	public function offsetUnset(mixed $offset): void
	{
		if ($this->isMutable === false) {
			throw new ImmutableCollectionException("Cannot unset an item of immutable collection.");
		}

		unset($this->items[$offset]);
	}

	/**
	 * Gets an iterator for the collection.
	 *
	 * @return Traversable
	 */
	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->items);
	}

	/**
	 * Checks if the collection is empty.
	 *
	 * @return bool
	 */
	public function isEmpty(): bool
	{
		return empty($this->items);
	}

	/**
	 * Gets the first item in the collection or return null if the collection is empty.
	 *
	 * @return ?Item
	 */
	public function first(): mixed
	{
		return reset($this->items) ?: null;
	}

	/**
	 * Gets the last item in the collection or return null if the collection is empty.
	 *
	 * @return ?Item
	 */
	public function last(): mixed
	{
		return end($this->items) ?: null;
	}

	/**
	 * Gets the item at the specified index in the collection.
	 *
	 * @param int $index The index to retrieve.
	 * @param bool $strict Indicates whether to throw an exception if the index does not exist.
	 * @return ?Item
	 * @throws ItemNotFoundException
	 */
	public function index(int $index, bool $strict = false): mixed
	{
		if (!$this->offsetExists($index) && $strict === true) {
			throw new ItemNotFoundException("Item at position [$index] does not exist in the collection.");
		}

		return $this->offsetGet($index);
	}

	/**
	 * Extracts a column from the items in the collection.
	 *
	 * @param int|string $column The column to extract.
	 * @param ?string $key The key to use as the index for the returned array.
	 * @return static
	 */
	public function column(string|int $column, string $key = null): static
	{
		$items = array_column($this->items, $column, $key);

		return new static($items);
	}

	/**
	 * Chunks the collection into smaller collections.
	 * Returns a new collection of type array.
	 *
	 * @param int $length The size of each chunk.
	 * @param bool $preserveKeys Whether to preserve the keys of the original collection.
	 * @return static
	 */
	public function chunk(int $length, bool $preserveKeys = false): static
	{
		$items = array_chunk($this->items, $length, $preserveKeys);

		return new static($items, Type::ARRAY);
	}

	/**
	 * Combines the collection with the keys provided.
	 *
	 * @param array|CollectionInterface $keys The keys to combine with the collection.
	 * @return static
	 */
	public function combine(array|CollectionInterface $keys): static
	{
		$keys = is_array($keys) ? $keys : iterator_to_array($keys);
		$items = $this->items;
		$items = array_combine($keys, $items);

		if ($this->isMutable) {
			$this->items = $items;
			return $this;
		}

		$collection = clone $this;
		$collection->items = $items;

		/** @var static */
		return $collection;
	}

	/**
	 * Checks if the collection contains a given item.
	 *
	 * @param Item $item The item to check for.
	 * @return bool
	 */
	public function has(mixed $item): bool
	{
		return in_array($item, $this->items);
	}

	/**
	 * Check if the key exists in the collection.
	 *
	 * @param string|int $key The key to check for.
	 * @return bool
	 */
	public function hasKey(string|int $key): bool
	{
		return array_key_exists($key, $this->items);
	}

	/**
	 * Gets a collection of the keys in the current collection.
	 *
	 * @return static
	 */
	public function keys(): static
	{
		return new static(array_keys($this->items));
	}

	/**
	 * Gets a collection of the values in the current collection.
	 *
	 * @return static
	 */
	public function values(): static
	{
		return new static(array_values($this->items));
	}

	/**
	 * Appends an item to the collection.
	 *
	 * @param Item $item The item to append.
	 * @return static<Item>
	 * @throws InvalidLiteralTypeException
	 * @throws InvalidTypeException
	 */
	public function append(mixed $item): static
	{
		$this->validateItemType($item);

		if ($this->isMutable) {
			$this->offsetSet(null, $item);

			return $this;
		}

		$collection = clone $this;
		$collection->items[] = $item;

		/** @var static<Item> */
		return $collection;
	}

	/**
	 * Prepends an item to the collection.
	 *
	 * @param Item $item The item to prepend.
	 * @return static<Item>
	 * @throws InvalidLiteralTypeException
	 * @throws InvalidTypeException
	 */
	public function prepend(mixed $item): static
	{
		$this->validateItemType($item);

		if ($this->isMutable) {
			array_unshift($this->items, $item);

			return $this;
		}

		$collection = clone $this;
		array_unshift($collection->items, $item);

		return $collection;
	}

	/**
	 * Removes the item at the specified key from the collection.
	 *
	 * @param int|string $key The key to unset.
	 * @return static
	 */
	public function unset(int|string $key): static
	{
		if ($this->isMutable) {
			$this->offsetUnset($key);

			return $this;
		}

		$collection = clone $this;
		unset($collection->items[$key]);

		return $collection;
	}

	/**
	 * Get the difference of two collections, merge them and return as a new collection
	 *
	 * @param CollectionInterface $collection The collection to compare with.
	 * @param bool $checkType Determine whether to check the type of two collections.
	 * @param Closure|callable|null $comparator A custom callback function for item comparison (optional).
	 * @return static
	 */
	public function diff(CollectionInterface $collection, bool $checkType = true, Closure|callable $comparator = null): static
	{
		if ($checkType) {
			$this->validateCollectionType($collection);
		}

		$a = array_udiff($this->items, $collection->toArray(), $comparator ?? $this->getComparisonCallback());
		$b = array_udiff($collection->toArray(), $this->items, $comparator ?? $this->getComparisonCallback());

		$newCollection = clone $this;
		$newCollection->items = [...$a, ...$b];
		$newCollection->type = $this->getType() !== $collection->getType() ? TYPE::MIXED : $this->getType();

		return $newCollection;
	}

	/**
	 * Get the difference of two collections while also checking the keys, merge them and return as a new collection
	 *
	 * @param CollectionInterface $collection The collection to compare with.
	 * @param bool $checkType Determine whether to check the type of two collections.
	 * @param Closure|callable|null $comparator A custom callback function for item comparison (optional).
	 * @return $this
	 */
	public function diffAssoc(CollectionInterface $collection, bool $checkType = true, Closure|callable $comparator = null): static
	{
		if ($checkType) {
			$this->validateCollectionType($collection);
		}

		if ($comparator) {
			$a = array_diff_uassoc($this->items, $collection->toArray(), $comparator);
			$b = array_diff_uassoc($collection->toArray(), $this->items, $comparator);
		} else {
			$a = array_diff_assoc($this->items, $collection->toArray());
			$b = array_diff_assoc($collection->toArray(), $this->items);
		}

		$newCollection = clone $this;
		$newCollection->items = array_unique([...$a, ...$b]);
		$newCollection->type = $this->getType() !== $collection->getType() ? TYPE::MIXED : $this->getType();

		return $newCollection;
	}

	/**
	 * Exchanges all keys with their associated values.
	 * Note that it will run type checking.
	 *
	 * @return $this
	 */
	public function flip(): static
	{
		$items = array_flip($this->items);

		if ($this->isMutable) {
			$this->items = $items;
			$this->validate();

			return $this;
		}

		$collection = clone $this;
		$collection->items = $items;
		$collection->validate();

		return $collection;
	}

	/**
	 * Get the intersection of two collections and return as a new collection.
	 *
	 * @param CollectionInterface $collection The collection to compare with.
	 * @param bool $checkType Determine whether to check the type of two collections.
	 * @param Closure|callable|null $comparator A custom callback function for item comparison (optional).
	 * @return static
	 * @throws InvalidTypeException If the collection types do not match.
	 */
	public function intersect(CollectionInterface $collection, bool $checkType = true, Closure|callable $comparator = null): static
	{
		if ($checkType) {
			$this->validateCollectionType($collection);
		}

		$items = $comparator
			? array_uintersect($this->items, $collection->toArray(), $comparator)
			: array_intersect($this->items, $collection->toArray());

		$newCollection = clone $this;
		$newCollection->items = $items;
		$newCollection->type = $this->getType() !== $collection->getType() ? TYPE::MIXED : $this->getType();

		return $newCollection;
	}

	/**
	 * Get the intersection of two collections based on key-value pairs and return as a new collection.
	 *
	 * @param CollectionInterface $collection The collection to compare with.
	 * @param bool $checkType Determine whether to check the type of two collections.
	 * @param Closure|callable|null $comparator A custom callback function for item comparison (optional).
	 * @return static
	 * @throws InvalidTypeException If the collection types do not match.
	 */
	public function intersectAssoc(CollectionInterface $collection, bool $checkType = true, Closure|callable $comparator = null): static
	{
		if ($checkType) {
			$this->validateCollectionType($collection);
		}

		$items = $comparator
			? array_uintersect_assoc($this->items, $collection->toArray(), $comparator)
			: array_intersect_assoc($this->items, $collection->toArray());

		$newCollection = clone $this;
		$newCollection->items = $items;
		$newCollection->type = $this->getType() !== $collection->getType() ? TYPE::MIXED : $this->getType();

		return $newCollection;
	}

	/**
	 * Map through the collection items.
	 *
	 * Note: Items that are objects will be modified no matter if the collection is mutable or not.
	 * This is due to the fact that objects are pass by reference and not by value.
	 *
	 * @param Closure|callable $callback The callable method to be called on every item.
	 * @param bool $checkType Determine whether to check the type of two collections.
	 * @return static
	 */
	public function map(Closure|callable $callback, bool $checkType = true): static
	{
		$items = array_map(function ($item) use ($callback, $checkType) {
			$item = $callback($item);
			if ($checkType) {
				$this->validateItemType($item);
			}

			return $item;
		}, $this->items);

		if ($this->isMutable) {
			$this->items = $items;
			$this->type = $checkType ? $this->getType() : Type::MIXED;
			return $this;
		}

		$collection = clone $this;
		$collection->items = $items;
		$collection->type = $checkType ? $collection->getType() : Type::MIXED;

		return $collection;
	}

	/**
	 * Merge two collections.
	 *
	 * @param CollectionInterface $collection The other collection to merge with.
	 * @param bool $checkType Determine whether to check the type of two collections.
	 * @return static
	 */
	public function merge(CollectionInterface $collection, bool $checkType = true): static
	{
		if ($checkType) {
			$this->validateCollectionType($collection);
		}

		$items = array_merge($this->items, $collection->toArray());

		if ($this->isMutable) {
			$this->items = $items;
			return $this;
		}

		$newCollection = clone $this;
		$newCollection->items = $items;
		$newCollection->type = $this->getType() !== $collection->getType() ? TYPE::MIXED : $this->getType();

		return $newCollection;
	}

	/**
	 * Calculate the product of values in an array.
	 *
	 * @return float|int
	 */
	public function product(): float|int
	{
		return array_product($this->items);
	}

	/**
	 * Iteratively reduce the collection to a single value using a callback function.
	 *
	 * @param Closure|callable $callback
	 * @param mixed|null $initial
	 * @return mixed
	 */
	public function reduce(Closure|callable $callback, mixed $initial = null): mixed
	{
		return array_reduce($this->items, $callback, $initial);
	}

	/**
	 * Reverse the collection items.
	 *
	 * @param bool $preserveKeys
	 * @return $this
	 */
	public function reverse(bool $preserveKeys = false): static
	{
		$items = array_reverse($this->items, $preserveKeys);

		if ($this->isMutable) {
			$this->items = $items;

			return $this;
		}

		$collection = clone $this;
		$collection->items = $items;

		return $collection;
	}

	/**
	 * Slice the collection starting from the given offset upto the given length.
	 *
	 * @param int $offset
	 * @param int|null $length
	 * @param bool $preserveKeys
	 * @return $this
	 */
	public function slice(int  $offset, ?int $length = null, bool $preserveKeys = false): static
	{
		$items = array_slice($this->items, $offset, $length, $preserveKeys);

		if ($this->isMutable) {
			$this->items = $items;

			return $this;
		}

		$collection = clone $this;
		$collection->items = $items;

		return $collection;
	}

	/**
	 * Calculate the sum of values in the collection.
	 *
	 * @return float|int
	 */
	public function sum(): float|int
	{
		return array_sum($this->items);
	}

	/**
	 * Validate the type of the given item.
	 *
	 * It will throw an exception if an invalid type is encountered.
	 *
	 * @param mixed $item
	 * @return void
	 * @throws InvalidLiteralTypeException
	 * @throws InvalidTypeException
	 */
	protected function validateItemType(mixed $item): void
	{
		if ($this->isValidType($item)) {
			return;
		}

		$expectedType = $this->getType();
		$actualType = $this->getItemType($item);

		if ($this->isLiteralType) {
			[$actualType, $expectedType] = $this->getActualAndExpectedTypeAsString($actualType, $expectedType);
			throw new InvalidLiteralTypeException("Invalid item type encountered, expecting literal type of [$expectedType], [$actualType] given.");
		}

		throw new InvalidTypeException("Invalid item type encountered, expecting type of [$expectedType], [$actualType] given.");
	}

	/**
	 * Validate the type of current collection against another collection.
	 *
	 * It will throw an exception if the two collection is not of the same type.
	 *
	 * @param CollectionInterface $collection
	 * @return void
	 * @throws InvalidTypeException
	 */
	protected function validateCollectionType(CollectionInterface $collection): void
	{
		if ($this->isLiteralType !== $collection->isLiteralType) {
			throw new InvalidTypeException("Collection type mismatch, one expects a literal type.");
		}

		if (($aType = $this->getType()) !== ($bType = $collection->getType())) {
			[$aType, $bType] = $this->getActualAndExpectedTypeAsString($aType, $bType);
			throw new InvalidTypeException("Collection type mismatch, expecting type of [$aType], [$bType] given.");
		}
	}

	/**
	 * Validates the type of each item in the collection against the expected type.
	 *
	 * @return void
	 * @throws InvalidLiteralTypeException
	 * @throws InvalidTypeException
	 */
	protected function validate(): void
	{
		if ($this->isEmpty()) {
			return;
		}

		foreach ($this->items as $item) {
			$this->validateItemType($item);
		}
	}

	/**
	 * Get a string representation of actual and expected type
	 *
	 * @param mixed $actual
	 * @param mixed $expected
	 * @return array
	 */
	protected function getActualAndExpectedTypeAsString(mixed $actual, mixed $expected): array
	{
		if ($this->isLiteralType) {
			return [json_encode($actual), json_encode($expected)];
		}

		return [$actual, $expected];
	}

	/**
	 * Checks if the given item is of a valid type.
	 *
	 * @param Item $item The item to check.
	 * @return bool
	 */
	protected function isValidType(mixed $item): bool
	{
		$actualType = $this->getItemType($item);
		$expectedType = $this->getType();

		return $expectedType === ''
			|| $expectedType === Type::MIXED
			|| $expectedType === Type::MIXED->value
			|| $expectedType === $actualType
			|| (is_string($expectedType) && $item instanceof $expectedType);
	}

	/**
	 * Gets the type of the given item.
	 *
	 * @param Item $item The item to get the type of.
	 * @return mixed
	 */
	protected function getItemType(mixed $item): mixed
	{
		if ($this->isLiteralType) {
			return $item;
		}

		return is_object($item) ? get_class($item) : gettype($item);
	}

	/**
	 * Get the callback for comparing the items of 2 collections.
	 *
	 * @return Closure
	 */
	protected function getComparisonCallback(): Closure
	{
		return function (mixed $a, mixed $b): int {
			if (is_object($a) && is_object($b)) {
				$a = spl_object_id($a);
				$b = spl_object_id($b);
			}

			if (stringable($a) && stringable($b)) {
				return $a <=> $b;
			}

			if (gettype($a) !== gettype($b)) {
				return -1;
			}

			return $a <=> $b;
		};
	}
}
