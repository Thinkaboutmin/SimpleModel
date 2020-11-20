<?php

require_once(dirname(__FILE__) . '/../config/database.php');

/**
 * Modelo de um objeto do banco.
 * @property Array $extensions
 */
abstract class Model
{
    /**
     * @var PDO $database
     */
    public static $database;

    /**
     * Campos de data precisam de uma formatação especifica,
     * coloque ela aqui. Formatações são realizas com format
     */
    protected $camps_with_dateformat = null;

    /**
     * Se a chave primária utiliza auto incremento.
     */
    protected $auto_increment = false;

    /**
     * Campos que podem ser nulos.
     */
    protected $nullables = [];

    /**
     * Extensão para outros modelos. Utilize em conjunto de Models com o $pseudo_model como true
     * @var Array $extensions
     */
    protected $extensions = [];

    /**
     * Modelo que faz parte de outro modelo porém não
     * possui uma tabela no banco de dados.
     *
     * Util para desagrupar uma tabela em vários modelos e criar
     * uma hierarquia entre os modelos.
     */
    protected $pseudo_model = false;

    /**
     * Pega a tabela do modelo.
     *
     * Caso a tabela seja outra modifique essa propriedade para
     * conseguir outro resultado.
     *
     * Iniciliazação feita no construtor
     */
    protected $table = null;

    /**
     * Nome do campo que é chave primária
     */
    protected $primary_key = null;

    /**
     * Name of the properties in the Model
     * @var Array $camps
     */
    protected $camps = [];

    public function insert()
    {
        $count = 0;
        foreach ($this->extensions as $extension) {
            if ($extension->pseudo_model) {
                $count += sizeof($extension->camps);
            }
        }

        $count += sizeof($this->camps);

        $query = 'INSERT INTO ' .  $this->table . ' (';
        $values = [];
        foreach ($this->camps as $camp) {
            if ($this->auto_increment && $this->primary_key == $camp) {
                if (is_null($this->$camp)) {
                    $count -= 1;
                    continue;
                }
            } else {
                $query .= $camp . ',';
            }

            if ($this->$camp instanceof ModelForeign) {
                array_push($values, $this->$camp->foreign_key);
                continue;
            } elseif ($this->$camp instanceof DateTime) {
                array_push($values, $this->$camp->format($this->camps_with_dateformat[$camp]));
                continue;
            }
            array_push($values, $this->$camp);
        }

        foreach ($this->extensions as $extension) {
            if ($extension->pseudo_model) {
                foreach ($extension->camps as $camp) {
                    $query .= $camp . ',';

                    if ($extension->$camp instanceof ModelForeign) {
                        array_push($values, $extension->$camp->foreign_key);
                        continue;
                    } elseif ($extension->$camp instanceof DateTime) {
                        array_push($values, $extension->$camp->format($extension->camps_with_dateformat[$camp]));
                        continue;
                    }

                    array_push($values, $extension->$camp);
                }
            }
        }

        if ($query[strlen($query) - 1] == ',') {
            $query = substr($query, 0, strlen($query) - 1);
        }

        $query .= ') VALUES (' . str_repeat('?,', $count - 1) . '?)';

        $query = Model::$database->prepare($query);
    
        for ($i = 0; $i < sizeof($values); ++$i) {
            if (is_bool($values[$i])) {
                $values[$i] = (Int)$values[$i];
            }
        }

        $result = $query->execute($values);

        if (!$result) {
            return $query->errorInfo();
        }

        // Pega o id de uma tabela auto incrementavel.
        // Funciona no MySQL e MariaDB
        if ($this->auto_increment) {
            $query = 'SELECT LAST_INSERT_ID()';
            $query = Model::$database->prepare($query);
            $result = $query->execute();

            if (!$result) {
                return $query->errorInfo();
            }

            $primary_key = $this->primary_key;
            $data = $query->fetchAll($fetch_argument=PDO::FETCH_NAMED);
            $this->$primary_key = $data[0]['LAST_INSERT_ID()'];
        }
        
        return [];
    }

    public function update()
    {
        $count = 0;
        foreach ($this->extensions as $extension) {
            if ($extension->pseudo_model) {
                $count += sizeof($extension->camps);
            }
        }

        $count += sizeof($this->camps);
        $values = [];

        $query = 'UPDATE ' .  $this->table . ' SET ';
        foreach ($this->camps as $camp) {
            $query .= $camp . '=?,';
            if ($this->$camp instanceof DateTime) {
                array_push($values, $this->$camp->format($this->camps_with_dateformat[$camp]));
                continue;
            } elseif ($this->$camp instanceof ModelForeign) {
                array_push($values, $this->$camp->foreign_key);
                continue;
            }

            array_push($values, $this->$camp);
        }

        foreach ($this->extensions as $extension) {
            if ($extension->pseudo_model) {
                foreach ($extension->camps as $camp) {
                    $query .= $camp . '=?,';

                    if ($extension->$camp instanceof DateTime) {
                        array_push($values, $extension->$camp->format($extension->camps_with_dateformat[$camp]));
                        continue;
                    } elseif ($extension->$camp instanceof ModelForeign) {
                        array_push($values, $extension->$camp->foreign_key);
                        continue;
                    }

                    array_push($values, $extension->$camp);
                }
            }
        }

        if ($query[strlen($query) - 1] == ',') {
            $query = substr($query, 0, strlen($query) - 1);
        }

        $primary = $this->primary_key;
        $query .= ' WHERE ' . $this->primary_key . '=' . $this->$primary;
        
        $query = Model::$database->prepare($query);
    
        for ($i = 0; $i < sizeof($values); ++$i) {
            if (is_bool($values[$i])) {
                $values[$i] = (Int)$values[$i];
            }
        }
        $result = $query->execute($values);

        if (!$result) {
            return $query->errorInfo();
        }

        return [];
    }

    public function delete()
    {
        $values = [];

        $query = 'DELETE FROM ' .  $this->table . ' WHERE ';
        foreach ($this->camps as $camp) {
            if (($this->$camp == null || $this->__get($camp) == null) && !in_array($camp, $this->nullables)) {
                continue;
            }
            $query .= $camp . '=? AND ';
            if ($this->$camp instanceof DateTime) {
                array_push($values, $this->$camp->format($this->camps_with_dateformat[$camp]));
                continue;
            } elseif ($this->$camp instanceof ModelForeign) {
                array_push($values, $this->$camp->foreign_key);
                continue;
            }

            array_push($values, $this->$camp);
        }

        foreach ($this->extensions as $extension) {
            if ($extension->pseudo_model) {
                foreach ($extension->camps as $camp) {
                    if (($extension->$camp == null || $extension->__get($camp) == null) && !in_array($camp, $extension->nullables)) {
                        continue;
                    }
                    $query .= $camp . '=? AND ';

                    if ($extension->$camp instanceof DateTime) {
                        array_push($values, $extension->$camp->format($extension->camps_with_dateformat[$camp]));
                        continue;
                    } elseif ($extension->$camp instanceof ModelForeign) {
                        array_push($values, $extension->$camp->foreign_key);
                        continue;
                    }

                    array_push($values, $extension->$camp);
                }
            }
        }

        $query = substr($query, 0, strlen($query) - 5);
        
        $query = Model::$database->prepare($query);
    
        for ($i = 0; $i < sizeof($values); ++$i) {
            if (is_bool($values[$i])) {
                $values[$i] = (Int)$values[$i];
            }
        }
        $result = $query->execute($values);

        if (!$result) {
            return $query->errorInfo();
        }
        
        return [];
    }

    /**
     * Pega o primeiro objeto a partir dos campos passados
     * e coloca os dados pegos no Model.
     */
    public function get($camps)
    {
        $query = 'SELECT * FROM ' . $this->table . ' WHERE ';
        
        $values = [];
        foreach (array_keys($camps) as $camp) {
            $query .= $camp . '=?,';
            array_push($values, $camps[$camp]);
        }

        if ($query[strlen($query) - 1] == ',') {
            $query = substr($query, 0, strlen($query) - 1);
        }

        $query = Model::$database->prepare($query);

        for ($i = 0; $i < sizeof($values); ++$i) {
            if (is_bool($values[$i])) {
                $values[$i] = (Int)$values[$i];
            }
        }

        $result = $query->execute($values);
        if (!$result) {
            return $query->errorInfo();
        }

        $result = $query->fetchAll($fetch_argument=PDO::FETCH_NAMED);
        if (!empty($result)) {
            $this->fromMap($result[0]);
        } else {
            // Garente que todos os campos vão refletir nada.
            $this->fromMap([]);
        }
        
        return $this;
    }

    /**
     * Pega os dados e retorna uma lista de Model.
     */
    public function all($camps = null)
    {
        $query = 'SELECT * FROM ' . $this->table;
        $values = [];
        if ($camps != null) {
            $query .= ' WHERE ';
            foreach (array_keys($camps) as $camp) {
                $query .= $camp . '=?,';
                array_push($values, $camps[$camp]);
            }

            if ($query[strlen($query) - 1] == ',') {
                $query = substr($query, 0, strlen($query) - 1);
            }

            for ($i = 0; $i < sizeof($values); ++$i) {
                if (is_bool($values[$i])) {
                    $values[$i] = (Int)$values[$i];
                }
            }
        }
        $query = Model::$database->prepare($query);
        $result = $query->execute($values);
        if (!$result) {
            return $query->errorInfo();
        }

        $objs = [];
        foreach ($query->fetchAll($fetch_argument=PDO::FETCH_NAMED) as $non_obj) {
            $class = get_class($this);
            $obj = new $class();
            $obj->fromMap($non_obj);
            array_push($objs, $obj);
        }
        
        return $objs;
    }

    /**
     * Válida os dados de um modelo.
     *
     * @return Array com chaves relacionadas aos atributos e seus erros.
     */
    abstract public function isValid();

    /**
     * Passa as informações de um array possuindos chaves com o mesmo nome do nosso atributo
     * com as informações necessarias para o modelo.
     *
     * @param $map Array com as chaves com o mesmo nome dos atributos do nosso objeto.
     *
     * @return void
     */
    public function fromMap($map)
    {
        if (is_array($map)) {
            foreach ($this->camps as $camp) {
                // Verificação livre de exceção
                if (array_key_exists($camp, $map) && property_exists($this, $camp)) {
                    if ($this->$camp instanceof Model) {
                        $this->$camp->fromMap($map[$camp]);
                    } elseif ($this->$camp instanceof ModelForeign) {
                        $this->$camp->foreign_key = $map[$camp];
                    } else {
                        $this->$camp = $map[$camp];
                    }
                } else {
                    if ($this->$camp instanceof ModelForeign) {
                        $this->$camp->foreign_key = null;
                        continue;
                    }
                    // Temos certeza de modificar todos os elementos
                    $this->$camp = null;
                }
            }
        }

        foreach ($this->extensions as $extension) {
            $extension->fromMap($map);
        }
    }

    /**
     * Transforma o Model em um array para poder ser serializado como JSON
     * ou outros formatos desejados.
     */
    public function toMap(): array
    {
        $array = [];

        foreach ($this->camps as $camp) {
            if ($this->$camp instanceof Model) {
                $array[$camp] = $this->$camp->toMap();
            } elseif ($this->$camp instanceof ModelForeign) {
                $array[$camp] = $this->$camp->foreign_key;
            } elseif ($this->$camp instanceof DateTime) {
                $array[$camp] = $this->$camp->format($this->camps_with_dateformat[$camp]);
            } else {
                $array[$camp] = $this->$camp;
            }
        }

        foreach ($this->extensions as $extension) {
            $extension_map = $extension->toMap();
            $array = array_merge($array, $extension_map);
        }

        return $array;
    }

    public function __get($string)
    {
        if (property_exists($this, $string)) {
            $foreign_key = ($this->$string instanceof ModelForeign);
            if (!$foreign_key) {
                return $this->$string;
            }

            $foreign = $this->$string;
            return $foreign->foreign_key;
        }

        foreach ($this->extensions as $extension) {
            $result = $extension->__get($string);
            if ($result != null) {
                return $result;
            }
        }
    }

    public function __set($string, $value)
    {
        if (property_exists($this, $string)) {
            $foreign_key = ($this->$string instanceof ModelForeign);
            if (!$foreign_key) {
                $this->$string = $value;
            } else {
                $foreign = $this->$string;
                $foreign->foreign_key = $value;
            }
        }

        foreach ($this->extensions as $extension) {
            $extension->__set($string, $value);
        }
    }
}
