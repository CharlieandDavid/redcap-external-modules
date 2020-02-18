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
$result = $module->framework->query('select count(*) from redcap_user_information', []);
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

if(is_array($event_ids)){
  $query->add('and')->addInClause('event_id', $event_ids);
}

if($record_id && $instance){
  $query->add('and record = ? and instance = ?', [$record_id, $instance]);
}

$result = $query->execute();
```

Query objects can also be used to get the number of affected rows since the `db_affected_rows()` method will not work with parameters:
```php
$query = $module->framework->createQuery();
$query->add('delete from redcap_data where record = ?', $record_id);
$query->execute();
$affected_rows = $query->affected_rows;
```

The following query object properties are supported:

Property | Description
-- | --
affected_rows | Returns the number of rows affected by the query just like `db_affected_rows()` does for queries without parameters.

The following query object methods are supported:

Method | Description
-- | --
add($sql[, $parameters]) | Adds any SQL to the query with an optional array of parameters
addInClause($column_name, $parameters) | Adds a SQL `IN` clause for the specified column and list of parameters.  An `OR IS NULL` clause is also added if any parameter in the list is `null`.  This is simply a convenience method to cover the most common use cases.  More complex `IN` clauses can still be built manually using `add()`.
execute() | Executes the SQL and parameters that have been added, and returns the standard [mysqli_result](https://www.php.net/manual/en/class.mysqli-result.php) object.

### Differences With & Without Parameters
Queries with parameters have a couple of behavioral differences from queries with an empty parameter array specified.  This is due to MySQLi historical quirks.  The differences are as follows:

- The `db_affected_rows()` method does not work for queries with parameters.  See the documentation above for an alternative.
- Numeric column values will return as the `int` type in PHP where they previously returned as `string`.  This may require changes to any type sensitive operations like triple equals checking.  The simplest solution to prevent potential issues without refactoring is to cast the numeric columns in either SQL or PHP.
    - In PHP you can cast all integer columns to strings manually, or by using the following utility method on each fetched row:
      - `$row = $module->framework->convertIntsToStrings($row);`
    - In SQL you can cast values individually.  For example:
      - Before: `select project_id`
      - After: &nbsp;&nbsp;`select cast(project_id as char) as project_id`.
