<?php

namespace Mollie\Repository;

use ObjectModel;
use PrestaShopCollection;
use PrestaShopException;

class AbstractRepository implements ReadOnlyRepositoryInterface
{
    /**
     * @var string
     */
    private $fullyClassifiedClassName;

    /**
     * @param string $fullyClassifiedClassName
     *
     */
    public function __construct($fullyClassifiedClassName)
    {
        $this->fullyClassifiedClassName = $fullyClassifiedClassName;
    }

    /**
     * @param array $keyValueCriteria
     * @return ObjectModel|null
     *
     * @throws PrestaShopException
     */
    public function findOneBy(array $keyValueCriteria)
    {
        $psCollection = new PrestaShopCollection($this->fullyClassifiedClassName);

        foreach ($keyValueCriteria as $field => $value) {
            $psCollection = $psCollection->where($field, '=', $value);
        }

        $first = $psCollection->getFirst();

        return false === $first ? null: $first;
    }

    /**
     * @param array $keyValueCriteria
     *
     * @return array
     * @throws PrestaShopException
     */
    public function findBy(array $keyValueCriteria)
    {
        $psCollection = new PrestaShopCollection($this->fullyClassifiedClassName);

        foreach ($keyValueCriteria as $field => $value) {
            $psCollection = $psCollection->where($field, '=', $value);
        }

        return $psCollection->getResults();
    }
}