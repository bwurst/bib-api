<?php

function filter($filter)
{
    $sqlfilter = '';
    $sqlfilter_params = array();
    if (isset($filter['subFilter'], $filter['subFilterConnective'])) {
        $subfilters = array();
        $connect = $filter['subFilterConnective'];
        if ($connect != 'OR' && $connect != 'AND') {
            api_send_error(500, 'unknown subFilterConnective');
        }
        foreach ($filter['subFilter'] as $f) {
            $relation = '=';
            if (isset($f['relation'])) {
                switch ($f['relation']) {
                    case 'like':
                        $relation = 'LIKE';
                        break;
                    case 'notlike':
                        $relation = 'NOT LIKE';
                        break;
                    case 'unequal':
                        $relation = '!=';
                        break;
                    case 'greater':
                        $relation = '>';
                        break;
                    case 'less':
                        $relation = '<';
                        break;
                    case 'greaterEqual':
                        $relation = '>=';
                        break;
                    case 'lessEqual':
                        $relation = '<=';
                        break;
                } 
            } 
            if (!isset($f['field']) || !array_key_exists('value', $f)) {
                api_send_error(500, 'incomplete subfilter');
            }
            if ($f['value'] === null) {
                if ($relation == '=') {
                    $subfilters[] = '`'.db_escape_string($f['field']).'` IS NULL';
                } else {
                    $subfilters[] = '`'.db_escape_string($f['field']).'` IS NOT NULL';
                }
            } else {
                $subfilters[] = '`'.db_escape_string($f['field']).'` '.$relation.' ?';
                $sqlfilter_params[] = $f['value'];
            }
        }
        $sqlfilter .= ' ('.implode(' '.$connect.' ', $subfilters).') ';
    } else {
        if (!isset($filter['field']) || !array_key_exists('value', $filter)) {
            api_send_error(500, 'incomplete filter');
        }
        $relation = '=';
        if (isset($filter['relation'])) {
            switch ($filter['relation']) {
                case 'like':
                    $relation = 'LIKE';
                    break;
                case 'notlike':
                    $relation = 'NOT LIKE';
                    break;
                case 'unequal':
                    $relation = '!=';
                    break;
                case 'greater':
                    $relation = '>';
                    break;
                case 'less':
                    $relation = '<';
                    break;
                case 'greaterEqual':
                    $relation = '>=';
                    break;
                case 'lessEqual':
                    $relation = '<=';
                    break;
            } 
        } 
        if ($filter['value'] === null) {
            if ($relation == '=') {
                $sqlfilter .= '`'.db_escape_string($filter['field']).'` IS NULL';
            } else {
                $sqlfilter .= '`'.db_escape_string($filter['field']).'` IS NOT NULL';
            }
        } else {
            $sqlfilter .= '`'.db_escape_string($filter['field']).'` '.$relation.' ?';
            $sqlfilter_params[] = $filter['value'];
        }
    }
    return array($sqlfilter, $sqlfilter_params);
}

function sorting(&$data)
{
    $order = '';
    $limit = '';
    if (isset($data['sort'])) {
        $sort = $data['sort'];
        $direction = 'ASC';
        if (isset($sort['order']) && $sort['order'] == 'DESC') {
            $direction = 'DESC';
        }
        $order = ' ORDER BY `'.db_escape_string($sort['field']).'` '.$direction;
    }
    if (isset($data['limit']) && is_numeric($data['limit'])) {
        $limit = ' LIMIT '.intval($data['limit']);
        if (isset($data['page']) && is_numeric($data['page'])) {
            $limit .= ' OFFSET '.(intval($data['page']) * intval($data['limit']));
        }
    }
    return $order.$limit;
}

