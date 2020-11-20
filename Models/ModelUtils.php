<?php

/*
 * Algumas utilidades para os modelos.
 */
class ModelUtils
{
    /*
     * Verifica se o valor é nulo ou vazio, returnando verdadeiro se sim
     * caso contrário falso.
     *
     * @param @string Valor a ser verificado
     *
     * @returns Boolean.
     */
    public static function isNullOrEmpty($string)
    {
        if ($string === null || (is_string($string) &&  empty(trim($string)))) {
            return true;
        }

        return false;
    }

    public static function isInt($number)
    {
        if (is_string($number) && is_numeric($number)) {
            $float = floatval($number);
            $int = intval($number);

            return $int == $float;
        }

        return is_int($number);
    }
}
