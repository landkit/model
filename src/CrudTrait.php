<?php

namespace LandKit\Model;

use DateTime;
use PDOException;

trait CrudTrait
{
    /**
     * @param array $data
     * @return int|string|null
     */
    protected function create(array $data)
    {
        try {
            $connect = Connect::instance($this->database);

            if (!$connect) {
                throw new PDOException(
                    Connect::fail()->getMessage(),
                    Connect::fail()->getCode(),
                    Connect::fail()->getPrevious()
                );
            }

            if (!$data) {
                throw new PDOException('Error registering: check the data.', 400);
            }

            if ($this->timestamps && static::CREATED_AT) {
                $data[static::CREATED_AT] = (new DateTime('now'))->format('Y-m-d H:i:s');
            }

            $columns = '';
            $values = '';

            if ($this->functions) {
                foreach ($this->functions as $column => $value) {
                    $columns .= "`{$column}`, ";
                    $values .= "{$value}, ";

                    unset($data[$column]);
                }
            }

            if ($data) {
                $columns .= '`' . implode('`, `', array_keys($data)) . '`';
                $values .= ':' . implode(', :', array_keys($data));
            }

            $query = "INSERT INTO {$this->table} ({$columns}) VALUES ({$values})";

            $statement = $connect->prepare($query);
            $statement->execute($this->filter($data));

            $primaryKey = $this->primaryKey;
            $lastInsertId = $connect->lastInsertId();

            return $lastInsertId ?: $this->data->$primaryKey;
        } catch (PDOException $e) {
            $this->fail = $e;
        }

        return null;
    }

    /**
     * @param array $data
     * @param string $terms
     * @param array|string $params
     * @return int|null
     */
    protected function update(array $data, string $terms, $params)
    {
        try {
            $connect = Connect::instance($this->database);

            if (!$connect) {
                throw new PDOException(
                    Connect::fail()->getMessage(),
                    Connect::fail()->getCode(),
                    Connect::fail()->getPrevious()
                );
            }

            if (!$data) {
                throw new PDOException('Error updating: Check the data.');
            }

            if ($this->timestamps && static::UPDATED_AT) {
                $data[static::UPDATED_AT] = (new DateTime('now'))->format('Y-m-d H:i:s');
            }

            $dataSet = [];

            if ($this->functions) {
                foreach ($this->functions as $column => $value) {
                    $dataSet[] = "`{$column}` = {$value}";

                    unset($data[$column]);
                }
            }

            if ($data) {
                foreach ($data as $bind => $value) {
                    $dataSet[] = "`{$bind}` = :{$bind}";
                }
            }

            $dataSet = implode(', ', $dataSet);

            if (is_string($params)) {
                parse_str($params, $array);
                $params = $array;
            }

            $statement = $connect->prepare("UPDATE {$this->table} SET {$dataSet} WHERE {$terms}");
            $statement->execute($this->filter(array_merge($data, $params)));

            return $statement->rowCount();
        } catch (PDOException $e) {
            $this->fail = $e;
        }

        return null;
    }

    /**
     * @param string $terms
     * @param array|string|null $params
     * @return bool
     */
    protected function delete(string $terms, $params): bool
    {
        try {
            $connect = Connect::instance($this->database);

            if (!$connect) {
                throw new PDOException(
                    Connect::fail()->getMessage(),
                    Connect::fail()->getCode(),
                    Connect::fail()->getPrevious()
                );
            }

            $statement = $connect->prepare("DELETE FROM {$this->table} WHERE {$terms}");

            if ($params) {
                if (is_string($params)) {
                    parse_str($params, $array);
                    $params = $array;
                }

                $statement->execute($params);
                return true;
            }

            $statement->execute();
            return true;
        } catch (PDOException $e) {
            $this->fail = $e;
        }

        return false;
    }

    /**
     * @param array $data
     * @return array
     */
    private function filter(array $data): array
    {
        $filter = [];

        foreach ($data as $key => $value) {
            $filter[$key] = is_null($value) ? null : filter_var($value, FILTER_DEFAULT);
        }

        return $filter;
    }
}
