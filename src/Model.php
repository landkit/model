<?php

namespace LandKit\Model;

use DateTime;
use PDO;
use PDOException;
use stdClass;

class Model
{
    /**
     * @var string
     */
    protected $database = 'default';

    /**
     * @var string
     */
    protected $table = '';

    /**
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * @var array
     */
    protected $required = [];

    /**
     * @var array
     */
    protected $uniqueColumns = [];

    /**
     * @var bool
     */
    protected $timestamps = true;

    /**
     * @var string
     */
    private $behaviorToSave;

    /**
     * @var string
     */
    private $statement;

    /**
     * @var array
     */
    private $params;

    /**
     * @var array
     */
    private $functions;

    /**
     * @var stdClass|null
     */
    private $data = null;

    /**
     * @var PDOException|null
     */
    private $fail = null;

    /**
     * @const string
     */
    const CREATED_AT = 'created_at';

    /**
     * @const string
     */
    const UPDATED_AT = 'updated_at';

    /**
     * Create new Model instance.
     *
     * @param string $behaviorToSave
     */
    public function __construct(string $behaviorToSave = 'create')
    {
        $this->behaviorToSave = $behaviorToSave;
        $this->statement = '';
        $this->params = [];
        $this->functions = [];
    }

    /**
     * @param string $name
     * @return float|int|string|null
     */
    public function __get(string $name)
    {
        return $this->data->$name ?? null;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set(string $name, $value)
    {
        if (is_null($this->data)) {
            $this->data = new stdClass();
        }

        $this->data->$name = $value;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->data->$name);
    }

    /**
     * @param string $name
     * @return void
     */
    public function __unset(string $name)
    {
        if (isset($this->data->$name)) {
            unset($this->data->$name);
        }
    }

    /**
     * @return bool
     */
    public function isUpdate(): bool
    {
        return $this->behaviorToSave == 'update';
    }

    /**
     * @return stdClass|null
     */
    public function data()
    {
        return $this->data;
    }

    /**
     * @return PDOException|null
     */
    public function fail()
    {
        return $this->fail;
    }

    /**
     * @param string $column
     * @param string $value
     * @return void
     */
    public function functionSql(string $column, string $value)
    {
        $this->functions[$column] = $value;
        $this->data->$column = $value;
    }

    /**
     * @param string $columns
     * @return Model
     */
    public function select(string $columns): Model
    {
        $this->statement = "SELECT {$columns} FROM {$this->table}";
        return $this;
    }

    /**
     * @param string $table
     * @param string $terms
     * @param string $type
     * @return Model
     */
    public function join(
        string $table,
        string $terms,
        string $type = 'INNER'
    ): Model {
        $this->statement .= " {$type} JOIN {$table} on {$terms}";
        return $this;
    }

    /**
     * @param string $table
     * @param string $terms
     * @return Model
     */
    public function leftJoin(string $table, string $terms): Model
    {
        return $this->join($table, $terms, 'LEFT');
    }

    /**
     * @param string $table
     * @param string $terms
     * @return Model
     */
    public function rightJoin(string $table, string $terms): Model
    {
        return $this->join($table, $terms, 'RIGHT');
    }

    /**
     * @param string $table
     * @param string $terms
     * @return Model
     */
    public function fullJoin(string $table, string $terms): Model
    {
        return $this->join($table, $terms, 'FULL OUTER');
    }

    /**
     * @param string $terms
     * @param array $params
     * @return Model
     */
    public function where(string $terms, array $params = []): Model
    {
        if (!$this->statement) {
            $this->select('*');
        }

        $this->statement .= " WHERE {$terms}";

        if ($params) {
            $this->params = $params;
        }

        return $this;
    }

    /**
     * @param string $value
     * @return Model
     */
    public function groupBy(string $value): Model
    {
        $this->statement .= " GROUP BY {$value}";
        return $this;
    }

    /**
     * @param string $value
     * @return Model
     */
    public function orderBy(string $value): Model
    {
        $this->statement .= " ORDER BY {$value}";
        return $this;
    }

    /**
     * @param int $value
     * @return Model
     */
    public function limit(int $value): Model
    {
        $this->statement .= " LIMIT {$value}";
        return $this;
    }

    /**
     * @param int $value
     * @return Model
     */
    public function offset(int $value): Model
    {
        $this->statement .= " OFFSET {$value}";
        return $this;
    }

    /**
     * @param int|string $value
     * @param string $columns
     * @return $this|mixed
     */
    public function findById($value, string $columns = '*')
    {
        if (!$this->statement) {
            $this->select($columns);
        }

        return $this->where('id = :id', ['id' => $value])->fetch();
    }

    /**
     * @param float|int|string $value
     * @param string $columns
     * @return $this|mixed
     */
    public function findByPrimaryKey($value, string $columns = '*')
    {
        if (!$this->statement) {
            $this->select($columns);
        }

        return $this->where("{$this->primaryKey} = :{$this->primaryKey}", [$this->primaryKey => $value])->fetch();
    }

    /**
     * @param bool $all
     * @return $this|mixed
     */
    public function fetch(bool $all = false)
    {
        try {
            $query = str_replace('{this.table}', $this->table, $this->statement);

            $statement = Connect::instance($this->database)->prepare($query);
            $statement->execute($this->params);

            $this->statement = '';
            $this->params = [];

            if (!$statement->rowCount()) {
                return null;
            }

            if ($all) {
                return strpos($query, ' JOIN ') !== false ?
                    $statement->fetchAll() :
                    $statement->fetchAll(PDO::FETCH_CLASS, static::class, ['behaviorToSave' => 'update']);
            }

            return strpos($query, ' JOIN ') !== false ?
                $statement->fetchObject() :
                $statement->fetchObject(static::class, ['behaviorToSave' => 'update']);
        } catch (PDOException $e) {
            $this->fail = $e;
            return null;
        }
    }

    /**
     * @return array
     */
    public function checkUniqueColumns(): array
    {
        $primaryKey = $this->primaryKey;
        $columns = [];
        $terms = '';
        $params = [];

        if ($this->behaviorToSave == 'update') {
            $terms = " AND {$primaryKey} != :{$primaryKey}";
            $params = [$primaryKey => $this->data->$primaryKey];
        }

        foreach ($this->uniqueColumns as $column) {
            if (isset($this->data->$column)) {
                $exists = $this->select($primaryKey)->where(
                    "{$column} = :{$column}{$terms}",
                    array_merge([$column => $this->data->$column], $params)
                )->fetch();

                if ($exists) {
                    $columns[] = $column;
                }
            }
        }

        return $columns;
    }

    /**
     * @param string $terms
     * @return int|null
     */
    public function rowCount(string $terms = '')
    {
        try {
            $this->select('COUNT(*)');

            $query = str_replace('{this.table}', $this->table, $this->statement);

            if ($terms) {
                $query .= ' WHERE ' . $terms;
            }

            $statement = Connect::instance($this->database)->query($query);
            $count = $statement->fetchColumn();

            $this->statement = '';

            return $count ?: 0;
        } catch (PDOException $e) {
            $this->fail = $e;
            return null;
        }
    }

    /**
     * @return bool
     */
    public function save(): bool
    {
        try {
            if ($this->required()) {
                throw new PDOException('Fill in the required fields.');
            }

            if ($this->behaviorToSave == 'create') {
                $primaryKeyValue = $this->create((array) $this->data);
            } elseif ($this->behaviorToSave == 'update') {
                if (!$this->primaryKey) {
                    throw new PDOException('Error updating! Check the data.');
                }

                $primaryKey = $this->primaryKey;
                $primaryKeyValue = $this->data->$primaryKey;

                $data = (array) $this->data;
                unset($data[$primaryKey]);

                $this->update($data, "{$primaryKey} = :{$primaryKey}", [$primaryKey => $primaryKeyValue]);
            } else {
                throw new PDOException('System error! If this warning persists, contact us.');
            }

            if (!$primaryKeyValue) {
                return false;
            }

            $this->behaviorToSave = 'update';
            $this->data = $this->findByPrimaryKey($primaryKeyValue)->data();

            return true;
        } catch (PDOException $e) {
            $this->fail = $e;
            return false;
        }
    }

    /**
     * @return bool
     */
    public function destroy(): bool
    {
        $primaryKey = $this->primaryKey;

        if (empty($this->data->$primaryKey)) {
            return false;
        }

        return $this->delete("{$primaryKey} = :{$primaryKey}", [$primaryKey => $this->data->$primaryKey]);
    }

    /**
     * @param array $data
     * @return string
     */
    protected function create(array $data): string
    {
        try {
            if (!$data) {
                throw new PDOException('Error registering! Check the data.');
            }

            if ($this->timestamps && static::CREATED_AT) {
                $data[static::CREATED_AT] = (new DateTime('now'))->format('Y-m-d H:i:s');
            }

            $columns = '';
            $values = '';

            if ($this->functions) {
                foreach ($this->functions as $column => $value) {
                    unset($data[$column]);

                    $columns .= "{$column}, ";
                    $values .= "{$value}, ";
                }
            }

            if ($data) {
                $values .= ':';

                foreach ($data as $column => $value) {
                    $columns .= "{$column}, ";
                    $values .= "{$column}, :";
                }

                $values = substr($values, 0, -3);
            } else {
                $values = substr($values, 0, -2);
            }

            $columns = substr($columns, 0, -2);
            $query = "INSERT INTO {$this->table} ({$columns}) VALUES ({$values})";

            $connect = Connect::instance($this->database);

            $statement = $connect->prepare($query);
            $statement->execute($this->filter($data));

            $primaryKey = $this->primaryKey;

            if (defined('CONF_DATABASE') && CONF_DATABASE[$this->database]['driver'] == 'mysql') {
                $lastInsertId = $connect->lastInsertId();
            } else {
                $lastInsertId = null;
            }

            return $lastInsertId ?: $this->data->$primaryKey;
        } catch (PDOException $e) {
            $this->fail = $e;
            return '';
        }
    }

    /**
     * @param array $data
     * @param string $terms
     * @param array $params
     * @return void
     */
    protected function update(array $data, string $terms, array $params)
    {
        try {
            if (!$data) {
                throw new PDOException('Error updating! Check the data.');
            }

            if ($this->timestamps && static::UPDATED_AT) {
                $data[static::UPDATED_AT] = (new DateTime('now'))->format('Y-m-d H:i:s');
            }

            $dataSet = [];

            if ($this->functions) {
                foreach ($this->functions as $column => $value) {
                    $dataSet[] = "{$column} = {$value}";
                    unset($data[$column]);
                }
            }

            foreach ($data as $bind => $value) {
                $dataSet[] = "{$bind} = :{$bind}";
            }

            $values = implode(', ', $dataSet);

            $query = "UPDATE {$this->table} SET {$values} WHERE {$terms}";

            $statement = Connect::instance($this->database)->prepare($query);
            $statement->execute($this->filter(array_merge($data, $params)));
        } catch (PDOException $e) {
            $this->fail = $e;
        }
    }

    /**
     * @param string $terms
     * @param array $params
     * @return bool
     */
    protected function delete(string $terms, array $params = []): bool
    {
        try {
            $query = "DELETE FROM {$this->table} WHERE {$terms}";
            $statement = Connect::instance($this->database)->prepare($query);

            if ($params) {
                $statement->execute($params);
                return true;
            }

            $statement->execute();

            return true;
        } catch (PDOException $e) {
            $this->fail = $e;
            return false;
        }
    }

    /**
     * @param string $terms
     * @param array $params
     * @return void
     */
    protected function updateData(string $terms, array $params = [])
    {
        $this->data = $this->where($terms, $params)->fetch()->data();
        $this->behaviorToSave = 'update';
    }

    /**
     * @param array $data
     * @return array
     */
    private function filter(array $data): array
    {
        $filter = [];

        foreach ($data as $column => $value) {
            $filter[$column] = is_null($value) ? null : filter_var($value, FILTER_DEFAULT);
        }

        return $filter;
    }

    /**
     * @return bool
     */
    private function required(): bool
    {
        $data = (array) $this->data;

        foreach ($this->required as $column) {
            if (!isset($data[$column]) || $data[$column] === '' || $data[$column] === null) {
                return true;
            }
        }

        return false;
    }
}