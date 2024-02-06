<?php

use CodeIgniter\Database\BaseBuilder;

/**
 * Applies sorting to a database query based on the provided order parameter.
 *
 * @param BaseBuilder $query        The database query builder object.
 * @param string      $order        The order parameter specifying the sort field and sort direction.
 * @param array|null  $mapping      An optional mapping array that maps sort field names to corresponding database column names.
 * @param array|null  $allowedField An optional array of allowed sort fields.
 *
 * @return BaseBuilder The modified database query object with the applied sorting.
 */
function applySorting(BaseBuilder $query, string $order, ?array $mapping = [], ?array $allowedField = []): BaseBuilder
{
    if (! empty($order)) {
        [$sortField, $sortDirection] = array_merge(explode(':', $order), ['DESC']);
        $sortDirection               = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';
        $sortField                   = $mapping[$sortField] ?? $sortField;

        if (empty($allowedField) || in_array($sortField, $allowedField, true)) {
            $query->orderBy($sortField, $sortDirection);
        }
    }

    return $query;
}

/**
 * Applies filters to a database query based on the provided filter parameters.
 *
 * @param BaseBuilder $query   The database query builder object.
 * @param array|null  $filters An array of filters to be applied to the query.
 * @param array|null  $mapping An optional mapping array that maps filter field names to corresponding database column names.
 *
 * @return BaseBuilder The modified database query object with the applied filters.
 */
function applyFilter(BaseBuilder $query, ?array $filters = [], ?array $mapping = []): BaseBuilder
{
    if (empty($filters)) {
        return $query;
    }

    foreach ($filters as $fieldName => $fieldValue) {
        $fieldName = $mapping[$fieldName] ?? trim($fieldName);

        if (! isset($fieldName, $fieldValue)) {
            continue;
        }
        if (is_array($fieldValue)) {
            foreach ($fieldValue as $operator => $value) {
                $value = trim($value);

                if (! isset($value)) {
                    continue;
                }

                if ($operator === 'like') {
                    $query->like($fieldName, $value);
                } else {
                    $comparison = match ($operator) {
                        'lte'   => '<=',
                        'gte'   => '>=',
                        'lt'    => '<',
                        'gt'    => '>',
                        default => '=',
                    };

                    $value = explode(',', $value);

                    if (count($value) > 1) {
                        $query->whereIn($fieldName, $value);
                    } else {
                        $query->where("{$fieldName} {$comparison}", $value[0]);
                    }
                }
            }
        } else {
            $value = trim($fieldValue);
            if (! isset($value)) {
                continue;
            }

            $query->where($fieldName, $value);
        }
    }

    return $query;
}
