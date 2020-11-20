<?php

class ModelForeign
{
    protected $model;
    /**
     * Campo onde a chave estrageira vai refereciar de outro modelo.
     */
    protected $foreign_camp;
    /**
     * Campo original do modelo que possui esta instância.
     */
    protected $original;

    public function __construct($model, $foreign_camp, $original = "")
    {
        $this->model = $model;
        $this->foreign_camp = $foreign_camp;
        $this->original = $original;
    }

    public function __toString()
    {
        return $this->foreign_value;
    }

    public function __set($name, $value): void
    {
        switch ($name) {
            case 'foreign_key':
                $camp = $this->foreign_camp;
                $this->model->$camp = $value;
                break;
        }
    }

    public function __get($name)
    {
        switch ($name) {
            case 'foreign_camp':
                return $this->$name;
            case 'model':
                return $this->$name;
            case 'foreign_key':
                $camp = $this->foreign_camp;
                return $this->model->$camp;
        }
    }

    /**
     * Valida somente o campo de chave estrangeira.
     */
    public function isValid()
    {
        $result = $this->model->isValid();
        // TODO: Talvez tenha uma grande utilização de processamento
        // TODO: nesse método de validação...
        if (key_exists($this->foreign_camp, $result)) {
            return [$this->original => $result[$this->foreign_camp]];
        }
    }
}
