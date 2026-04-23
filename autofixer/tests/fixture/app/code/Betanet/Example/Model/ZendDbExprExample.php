<?php

class ZendDbExprExample
{
    public function getCountExpr()
    {
        return new \Zend_Db_Expr('COUNT(*)');
    }

    public function getIfnullExpr()
    {
        return new Zend_Db_Expr('IFNULL(foo, 0)');
    }

    public function getConcatExpr($fields)
    {
        return new \Zend_Db_Expr('CONCAT(' . implode(',', $fields) . ')');
    }

    public function getGroupConcatExpr()
    {
        return new \Zend_Db_Expr('GROUP_CONCAT(DISTINCT entity_id)');
    }
}