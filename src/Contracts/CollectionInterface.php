<?php

declare(strict_types=1);

namespace Inspira\Collection\Contracts;

use ArrayAccess;
use Closure;
use Countable;
use Inspira\Contracts\Arrayable;
use IteratorAggregate;

/**
 * Interface CollectionInterface
 *
 * Represents an interface for a collection of items with various utility methods for manipulation and filtering.
 *
 * @package Inspira\Collection
 */
interface CollectionInterface extends IteratorAggregate, ArrayAccess, Countable, Arrayable, WhereInterface
{
	/**
	 * Get the type of the items that can be stored in the collection.
	 *
	 * @return mixed
	 */
	public function getType(): mixed;

	/**
	 * Get the first item in the collection.
	 *
	 * @return mixed The first item.
	 */
	public function first(): mixed;

	/**
	 * Get the last item in the collection.
	 *
	 * @return mixed The last item.
	 */
	public function last(): mixed;

	/**
	 * Get an item at a specific index.
	 *
	 * @param int $index The index of the item.
	 * @param bool $strict Whether to throw an exception if the index is out of bounds.
	 *
	 * @return mixed|null The item at the specified index.
	 */
	public function index(int $index, bool $strict = true): mixed;

	/**
	 * Get the values of a given column.
	 *
	 * @param string $column The name of the column.
	 * @param ?string $key The name of the key column.
	 *
	 * @return static A new collection with the values of the specified column.
	 */
	public function column(string $column, string $key = null): static;

	/**
	 * Chunk the collection into smaller chunks.
	 *
	 * @param int $length The size of each chunk.
	 * @param bool $preserveKeys Whether to preserve the keys of the original collection.
	 *
	 * @return static A new collection with the chunked data.
	 */
	public function chunk(int $length, bool $preserveKeys = false): static;

	/**
	 * Combine the given keys to the current collection item
	 *
	 * @param array|CollectionInterface $keys The keys to use.
	 *
	 * @return static A new collection with the specified keys.
	 */
	public function combine(array|CollectionInterface $keys): static;

	/**
	 * Determine if the collection has a given item.
	 *
	 * @param mixed $item The item to search for.
	 *
	 * @return bool True if the collection has the specified item, false otherwise.
	 */
	public function has(mixed $item): bool;

	/**
	 * Get the keys of the collection items.
	 *
	 * @return static A new collection with the keys of the original collection.
	 */
	public function keys(): static;

	/**
	 * Get the values of the collection items.
	 *
	 * @return static A new collection with the values of the original collection.
	 */
	public function values(): static;

	/**
	 * Append an item to the end of the collection.
	 *
	 * @param mixed $item The item to append.
	 *
	 * @return static The modified collection.
	 */
	public function append(mixed $item): static;

	/**
	 * Prepend an item to the beginning of the collection.
	 *
	 * @param mixed $item The item to prepend.
	 *
	 * @return static A new collection with the prepended value.
	 */
	public function prepend(mixed $item): static;

	/**
	 * Remove an item from the collection by key.
	 *
	 * @param int|string $key The key of the item to remove.
	 *
	 * @return static A new collection with the specified item removed.
	 */
	public function unset(int|string $key): static;

	/**
	 * Filter the collection items based on the given callback.
	 *
	 * @param callable|Closure $callback The callback that implements how the collection is filtered.
	 * @return static A new collection with the filtered items.
	 */
	public function filter(callable|Closure $callback): static;

	/**
	 * Dynamically retrieve the value of an item in the collection.
	 *
	 * @param string $name The name of the item.
	 *
	 * @return mixed The value of the specified item.
	 */
	public function __get(string $name): mixed;
}
