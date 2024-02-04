<?php

declare(strict_types=1);

namespace Inspira\Collection\Contracts;

use Closure;

/**
 * Interface WhereInterface
 *
 * This interface defines methods for building WHERE clauses in a collection.
 *
 * @package Inspira\Collection\Contracts
 */
interface WhereInterface
{
	/**
	 * Add a basic where clause to the collection.
	 *
	 * @param string|Closure $column The column to compare or a closure for advanced conditions.
	 * @param mixed $comparison The comparison operator or the value if the 3rd parameter is not provided.
	 * @param mixed $value The value to compare if a comparison operator is provided.
	 * @return static
	 */
	public function where(string|Closure $column, mixed $comparison = null, mixed $value = null): static;

	/**
	 * Filter the collection by checking if the item contains the given value.
	 *
	 * @param string $column The column to compare.
	 * @param string $value The value to compare for a "like" condition.
	 * @return static
	 */
	public function whereLike(string $column, string $value): static;

	/**
	 * Filter the collection by checking if the item does not contain the given value.
	 *
	 * @param string $column The column to compare.
	 * @param string $value The value to compare for a "not like" condition.
	 * @return static
	 */
	public function whereNotLike(string $column, string $value): static;

	/**
	 * Filter the collection by checking if the item is null.
	 *
	 * @param string $column The column to check for null.
	 * @return static
	 */
	public function whereNull(string $column): static;

	/**
	 * Filter the collection by checking if the item is not null.
	 *
	 * @param string $column The column to check for not null.
	 * @return static
	 */
	public function whereNotNull(string $column): static;

	/**
	 * Filter the collection by checking if the item is in between of lower bound and upper bound.
	 *
	 * @param string $column The column to check for a range.
	 * @param mixed $lowerBound The lower bound of the range.
	 * @param mixed $upperBound The upper bound of the range.
	 * @return static
	 */
	public function whereBetween(string $column, mixed $lowerBound, mixed $upperBound): static;

	/**
	 * Filter the collection by checking if the item is not in between of lower bound and upper bound.
	 *
	 * @param string $column The column to check for not being in a range.
	 * @param mixed $lowerBound The lower bound of the range.
	 * @param mixed $upperBound The upper bound of the range.
	 * @return static
	 */
	public function whereNotBetween(string $column, mixed $lowerBound, mixed $upperBound): static;

	/**
	 * Filter the collection by checking if the item is in the given array.
	 *
	 * @param string $column The column to check for being in an array of values.
	 * @param array $values The array of values.
	 * @return static
	 */
	public function whereIn(string $column, array $values): static;

	/**
	 * Filter the collection by checking if the item is not in the given array.
	 *
	 * @param string $column The column to check for not being in an array of values.
	 * @param array $values The array of values.
	 * @return static
	 */
	public function whereNotIn(string $column, array $values): static;
}
