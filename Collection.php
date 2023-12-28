<?php

declare(strict_types=1);

namespace Inspira\Collection;

use ArrayObject;
use Inspira\Collection\Exceptions\CollectionItemNotFoundException;
use Inspira\Collection\Exceptions\CollectionNotAccessibleException;
use Inspira\Contracts\Arrayable;
use OutOfBoundsException;
use ReturnTypeWillChange;
use Traversable;

class Collection extends ArrayObject implements Arrayable
{
	final public function __construct(object|array $array = [], int $flags = 0, string $iteratorClass = "ArrayIterator")
	{
		parent::__construct($array, $flags, $iteratorClass);
	}

	public static function make(array|object $data): static
	{
		return new static($data);
	}

	public function first(): mixed
	{
		return $this->index(0, false);
	}

	public function last(): mixed
	{
		return $this->index($this->getIterator()->count() - 1, false);
	}

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

	public function column(string $name, string $key = null): static
	{
		return new static(array_column($this->toArray(), $name, $key));
	}

	public function chunk(int $length, bool $preserveKeys = false): static
	{
		return new static(array_chunk($this->toArray(), $length, $preserveKeys));
	}

	public function withKeys(array|Traversable $keys): static
	{
		$keys = is_array($keys) ? $keys : iterator_to_array($keys);

		return new static(array_combine($keys, $this->toArray()));
	}

	public function has(array|Traversable $item): bool
	{
		$item = is_array($item) ? $item : iterator_to_array($item);

		return in_array($item, $this->toArray());
	}

	public function keys(): static
	{
		return new static(array_keys($this->toArray()));
	}

	public function values(): static
	{
		return new static(array_values($this->toArray()));
	}

	#[ReturnTypeWillChange]
	public function append(mixed $value): static
	{
		parent::append($value);

		return $this;
	}

	public function prepend(mixed $value): static
	{
		$items = $this->toArray();
		array_unshift($items, $value);

		return new static($items);
	}

	public function unset(int|string $key): static
	{
		$items = $this->toArray();
		unset($items[$key]);

		return new static($items);
	}

	/**
	 * @param mixed $filters
	 * $filters should be an array [column => value] format
	 * OR
	 * a scalar value that can be cast to string
	 *
	 * @param bool $strict
	 * @return $this
	 */
	public function where(mixed $filters, bool $strict = true): static
	{
		return $this->filter($filters, $strict, FilterEnum::WHERE);
	}

	/**
	 * @param mixed $filters
	 * $filters should be an array [column => value] format
	 * OR
	 * a scalar value that can be cast to string
	 *
	 * @param bool $strict
	 * $strict could be use if you want to search with match case
	 *
	 * @return $this
	 */
	public function like(mixed $filters, bool $strict = false): static
	{
		return $this->filter($filters, $strict, FilterEnum::LIKE);
	}

	public function toArray(): array
	{
		return iterator_to_array($this);
	}

	/**
	 * @param string $name
	 * @return false|mixed
	 * @throws CollectionItemNotFoundException
	 * @throws CollectionNotAccessibleException
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

	private function isTraversable(mixed $data): bool
	{
		return is_array($data) || is_object($data) || $data instanceof Traversable;
	}

	private function filter(mixed $filters, bool $strict, FilterEnum $type): static
	{
		$filters = $filters instanceof Traversable ? iterator_to_array($filters) : $filters;

		$result = array_filter($this->toArray(), function ($item) use ($filters, $strict, $type) {
			// Check if collection item is a stringable value and the filter is not an array
			if (stringable($item) && !is_array($filters)) {
				$itemValue = $strict === false ? strtolower((string) $item) : $item;
				$filterValue = $strict === false ? strtolower((string) $filters) : $filters;

				return match (true) {
					$type === FilterEnum::LIKE  => str_contains($itemValue, $filterValue),
					$strict === true            => $itemValue === $filterValue,
					$strict === false           => $itemValue == $filterValue,
					default                     => false
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

				$itemValue = $strict === false ? strtolower((string) $item[$key]) : $item[$key];
				$filterValue = $strict === false ? strtolower((string) $value) : $value;

				if ($type === FilterEnum::LIKE && !str_contains($itemValue, $filterValue)) {
					return false;
				}

				if ($type === FilterEnum::WHERE) {
					return match (true) {
						$strict === true && $itemValue !== $filterValue,
						$strict === false && $itemValue != $filterValue => false,
						default                                         => true
					};
				}
			}

			return true;
		});

		return new static($result);
	}
}
