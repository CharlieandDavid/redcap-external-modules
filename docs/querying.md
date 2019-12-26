## Querying The Database

The module framework is designed to encourage parameterized queries because they are commonly considered the [simplest and most effective way](https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html) to prevent the quite common [SQL injection attack](https://www.owasp.org/index.php/SQL_Injection).  To ensure protection, all dynamic values in queries should be passed as parameters instead of being manually appended to the query string.  This includes user input, GET/POST data, values sourced from the database, etc.  See the **Breaking Changes** for [Framework Version 4](framework/v4.md) for the differences between parameterized & traditional queries. 


### Basic Queries

Here is a basic query example using the `query()` method:
```php
$result = $module->framework->query(
  '
    select *
    from redcap_data
    where
      project_id = ?
      and record = ?
  ',
  [
    $project_id,
    $record_id
  ]
);
```
In the uncommon case of queries that really should not have any parameters, an empty array must be specificed to show that the use of parameters was seriously considered:
```php
$result = $module->query('select count(*) from redcap_user_information', []);
```

### Query Objects
For cases where basic query syntax is insufficient, a query object can be used to build any complex query using parameters:
```php
$query = $module->framework->createQuery();

$query->add('
  select *
  from redcap_data
  where
    project_id = ?
', $project_id);

if(!empty($event_ids)){
  $query->addInClause('event_id', $event_ids);
}

if($record_id && $instance){
  $query->add('and record = ? and instance = ?', [$record_id, $instance]);
}

$result = $query->execute();
```

Query objects can also be used to get the number of affected rows since the `db_affected_rows()` method will not work with parameters:
```php
$query = $module->createQuery();
$query->add('delete from redcap_data where record = ?', $record_id);
$query->execute();
$affected_rows = $query->getStatement()->affected_rows;
```

The following query object methods are supported:

Method | Description
-- | --
add($sql[, $parameters]) | Adds any SQL to the query with an optional array of parameters
addInClause($column_name, $parameters) | Adds a SQL `IN` clause for the specified column and list of parameters.  An `OR IS NULL` clause is also added if any parameter in the list is `null`.  This is simply a convenience method to cover the most common use cases.  More complex `IN` clauses can still be built manually using `add()`.
execute() | Executes and returns the query result for the SQL and parameters that have been added.
getStatement() | Returns the statement object used to allow access to `affected_rows` and other advanced functionality.


