<?php

namespace LandKit\Model;

use PDO;
use PDOException;
use stdClass;

class Model
{
    use CrudTrait;

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
     * @var array|null
     */
    protected $required = null;

    /**
     * @var bool
     */
    protected $timestamps = true;

    /**
     * @var string|null
     */
    private $statement = null;

    /**
     * @var array|string|null
     */
    private $params = null;

    /**
     * @var array|null
     */
    private $functions = null;

    /**
     * @var object|null
     */
    private $data = null;

    /**
     * @var PDOException|null
     */
    private $fail = null;

    /**
     * @var string
     */
    private $behaviorToSave;

    /**
     * @const string
     */
    const CREATED_AT = 'created_at';

    /**
     * @const string
     */
    const UPDATED_AT = 'updated_at';

    /**
     * Model constructor.
     *
     * @param string $behaviorToSave
     */
    public function __construct(string $behaviorToSave = 'create')
    {
        $this->behaviorToSave = $behaviorToSave;
    }

    /**
     * @param string $name
     * @return mixed
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
     * @param int $mode
     * @return array|null
     */
    public function columns(int $mode = PDO::FETCH_OBJ)
    {
        $statement = Connect::instance($this->database)->prepare("DESCRIBE {$this->table}");
        $statement->execute($this->params);
        return $statement->fetchAll($mode);
    }

    /**
     * @return object|null
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
    public function join(string $table, string $terms, string $type = 'INNER'): Model
    {
        if (!$this->statement) {
            $this->select('*');
        }

        $this->statement .= "{$type} JOIN {$table} ON {$terms}";
        return $this;
    }

    /**
     * @param string $table
     * @param string $terms
     * @return Model
     */
    public function letJoin(string $table, string $terms): Model
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
        return $this->join($table, $terms, 'FULL');
    }

    /**
     * @param string $terms
     * @param array|string|null $params
     * @return Model
     */
    public function where(string $terms, $params = null): Model
    {
        if (!$this->statement) {
            $this->select('*');
        }

        if (is_string($params)) {
            parse_str($params, $this->params);
        } else {
            $this->params = $params;
        }

        $this->statement .= " WHERE {$terms}";
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
     * @return $this|null
     */
    public function findByPrimaryKey($value, string $columns = '*')
    {
        return $this
            ->select($columns)
            ->where("`{$this->primaryKey}` = :{$this->primaryKey}", [$this->primaryKey => $value])
            ->fetch();
    }

    /**
     * @param int|string $value
     * @param string $columns
     * @return $this|null
     */
    public function findById($value, string $columns = '*')
    {
        return $this->select($columns)->where('id = :id', ['id' => $value])->fetch();
    }

    /**
     * @param bool $all
     * @return array|$this|null
     */
    public function fetch(bool $all = false)
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

            $query = strstr($this->statement, '{this.table}')
                ? str_replace('{this.table}', $this->table, $this->statement)
                : $this->statement;

            $statement = $connect->prepare($query);
            $statement->execute($this->params);

            $this->statement = null;
            $this->params = null;

            if (!$statement->rowCount()) {
                return null;
            }

            if ($all) {
                return strstr($query, 'JOIN')
                    ? $statement->fetchAll()
                    : $statement->fetchAll(PDO::FETCH_CLASS, static::class, ['behaviorToSave' => 'update']);
            }

            return strstr($query, 'JOIN')
                ? $statement->fetchObject()
                : $statement->fetchObject(static::class, ['behaviorToSave' => 'update']);
        } catch (PDOException $e) {
            $this->fail = $e;
        }

        return null;
    }

    /**
     * @param string|null $terms
     * @param array|string|null $params
     * @return int
     */
    public function rowCount(string $terms = null, $params = null): int
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

            $this->select('COUNT(*)');

            $query = str_replace('{this.table}', $this->table, $this->statement);

            if ($terms) {
                $query .= " WHERE {$terms}";
            }

            if ($params && is_string($params)) {
                parse_str($params, $this->params);
            }

            $statement = $connect->prepare($query);
            $count = $statement->execute($this->params);

            $this->statement = null;
            $this->params = null;

            return $count;
        } catch (PDOException $e) {
            $this->fail = $e;
        }

        return 0;
    }

    /**
     * @return bool
     */
    public function save(): bool
    {
        try {
            if ($this->required()) {
                throw new PDOException('Fill in the required fields.', 400);
            }

            if ($this->isUpdate()) {
                if (!$this->primaryKey) {
                    throw new PDOException('Error updating: primary key not defined.', 500);
                }

                $primaryKey = $this->primaryKey;
                $primaryKeyValue = $this->data->$primaryKey;

                if (!$primaryKeyValue) {
                    throw new PDOException('Error updating: primary key value not defined.', 500);
                }

                $save = $this->update(
                    $this->safe(),
                    "`{$primaryKey}` = :{$primaryKey}",
                    [$primaryKey => $primaryKeyValue]
                );
            } elseif ($this->isCreate()) {
                $primaryKeyValue = $this->create($this->safe());
                $save = $primaryKeyValue;
            } else {
                throw new PDOException('System error: If this warning persists, contact us.', 500);
            }

            if (is_null($save)) {
                return false;
            }

            $this->data = $this->findByPrimaryKey($primaryKeyValue)->data();

            return true;
        } catch (PDOException $e) {
            $this->fail = $e;
        }

        return false;
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

        return $this->delete("`{$primaryKey}` = :{$primaryKey}", [$primaryKey => $this->data->$primaryKey]);
    }

    /**
     * @return bool
     */
    protected function isCreate(): bool
    {
        return $this->behaviorToSave == 'create';
    }

    /**
     * @return bool
     */
    protected function isUpdate(): bool
    {
        return $this->behaviorToSave == 'update';
    }

    /**
     * @return bool
     */
    protected function required(): bool
    {
        $data = (array) $this->data;

        foreach ($this->required as $field) {
            if (!isset($data[$field]) || (!$data[$field] && !is_int($data[$field]))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array|null
     */
    protected function safe()
    {
        $safe = (array) $this->data;
        unset($safe[$this->primaryKey]);
        return $safe;
    }
}
