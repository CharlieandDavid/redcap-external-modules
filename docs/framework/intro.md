## External Module Methods & Framework Versioning

#### Introduction to Module Framework Versioning

The versioning feature of the **External Module Framework** allows for backward compatibility while the framework changes over time.  New modules should specify the `framework-version` in `config.json` as follows:
 
```
{
  ...
  "framework-version": #,
}
```

...where the `#` is replaced by the latest framework version number listed below (always an integer).  If a `framework-version` is not specified, a module will use framework version `1`.

To allow existing modules to remain backward compatible, a new framework version is released each time a breaking change is made.  These breaking changes are documented at the top of each version page below.  Module authors have the option to update existing modules to later framework versions and address breaking changes if/when they choose to do so.
 
<br/>

#### Framework Versions vs REDCap Versions

Specifying a module framework version has implications for the minimum REDCap version. A module's config.json should specify a `redcap-version-min` at least as high as that needed to get the framework code it requires.

The frameworks were released in these REDCap versions:

|Framework Versions|First Standard Release|First LTS Release|
|----------------- |------|-----|
|[Version 3](v3.md)|9.1.1 |9.1.3|
|[Version 2](v2.md)|8.11.6|9.1.3|
|[Version 1](v1.md)|8.0.0 |8.1.2|

#### Methods Provided by the Framework
The following methods are available via the `framework` object (e.g. `$module->framework->getModuleName()` or `$module->framework->records->lock()`).  Older methods are also accessible directly on the module class, but accessing them this way when using framework versions other than [Version 1](v1.md) is **deprecated** since implementation specifics may have changed to fix issues in certain edge cases.

We are working on an automated way to fill in the REDCap version numbers below.  It's not as easy as it sounds because the framework changes could be committed weeks before they make it into a REDCap release.

Method  | Minimum Versions<br>`REDCap`<br>`Framework` | Description 
------- | ------------------------- | ----------- 
addAutoNumberedRecord([$pid])) | `?.?.?`<br>`1` | Creates the next auto numbered record and returns the record id.  If the optional PID parameter is not specified, the current PID will be automatically detected.
convertIntsToStrings($row)) | `?.?.?`<br>`2` | Returns a copy of the specified array with any integer values cast to strings.  This method is intended to aid in converting queries to use parameters with minimal refactoring.
createDAG($name)) | `?.?.?`<br>`1` | Creates a DAG with the specified name, and returns it's ID.
createPassthruForm($projectId, $recordId[, $surveyFormName, $eventId])) | `?.?.?`<br>`1` | Outputs the HTML for opening/continuing the survey submission for the specified record.  If a return code is required, a link is returned.  Otherwise, a self submitting form is returned.
createProject($title, $purpose, [, $project_note])) | `?.?.?`<br>`2` | Creates a new redcap project and returns the project id.
delayModuleExecution()) | `?.?.?`<br>`1` | When called within a hook, this method causes the current hook to be "delayed", which means it will be called again after all other enabled modules (that define that hook) have executed.  This allows modules to interact with each other to control their run order.  For example, one module may wait for a second module to set a field value before it finishes executing.  A boolean value of `true` is returned if the hook was successfully delayed, or `false` if the hook cannot be delayed any longer and this is the module's last chance to perform any required actions.  If the delay was successful, hooks normally `return;` immediately after calling this method to stop the current execution of hook.
deleteDAG($dagId)) | `?.?.?`<br>`1` | Given a DAG ID, deletes the DAG and all Users and Records assigned to it.
exitAfterHook()) | `?.?.?`<br>`1` | Calling this method inside of a hook will schedule PHP's exit() function to be called after ALL modules finish executing for the current hook.  Do NOT call die() or exit() manually afterward (the framework will call it for you).
getChoiceLabel($params)) | `?.?.?`<br>`1` | Given an associative array, get the label associated with the specified choice value for a particular field. See the following example:<br> $params = array('field_name'=>'my_field', 'value'=>3, 'project_id'=>1, 'record_id'=>3, 'event_id'=>18, 'survey_form'=>'my_form', 'instance'=>2);
getChoiceLabels($fieldName[, $pid])) | `?.?.?`<br>`1` | Returns an array mapping all choice values to labels for the specified field.
getConfig()) | `?.?.?`<br>`1` | get the config for the current External Module; consists of config.json and filled-in values
getEventId()) | `?.?.?`<br>`2` | Returns the current event ID.  If an 'event_id' GET parameter is specified, it will be returned.  If not, and the project only has a single event, that event's ID will be returned.  If no 'event_id' GET parameter is specified and the project has multiple events, an exception will be thrown.
getFieldLabel($fieldName)) | `?.?.?`<br>`1` | Returns the label for the specified field name.
getJavascriptModuleObjectName()) | `?.?.?`<br>`2` | Returns the name of the javascript object for this module.
getModuleDirectoryName()) | `?.?.?`<br>`1` | get the directory name of the current external module
getModuleName()) | `?.?.?`<br>`1` | get the name of the current external module
getModulePath()) | `?.?.?`<br>`1` | Get the path of the current module directory (e.g., /var/html/redcap/modules/votecap_v1.1/)
getProject([$project_id])) | `?.?.?`<br>`2` | Returns a `Project` object for the given project ID, or the current project if no ID is specified.  This `Project` object is documented below.
getProjectId()) | `?.?.?`<br>`1` | A convenience method for returning the current project id.
getProjectsWithModuleEnabled()) | `?.?.?`<br>`2` | Returns an array of project ids for which the  current module is enabled (especially useful in cron jobs). 
getProjectSetting($key&nbsp;[,&nbsp;$pid])) | `?.?.?`<br>`1` | Returns the value stored for the specified key for the current project if it exists.  For non-repeatable settings, `null` is returned if no value is set.  For repeatable settings, an array with a single `null` value is returned if no value is set.  In most cases the project id can be detected automatically, but it can optionally be specified as a parameter instead.
getProjectSettings([$pid])) | `?.?.?`<br>`1` | Gets all project settings as an array.  Useful for cases when you may be creating a custom config page for the external module in a project.
getPublicSurveyUrl()) | `?.?.?`<br>`1` | Returns the public survey url for the current project.
getQueryLogsSql($sql)) | `?.?.?`<br>`1` | Returns the raw SQL that would run if the supplied parameter was passed into **queryLogs()**. 
getRecordId()) | `?.?.?`<br>`1` | Returns the current record id if called from within a hook that includes the record id.
getRecordIdField([$pid])) | `?.?.?`<br>`3` | Returns the name of the record ID field. Unlike the same method on the `REDCap` class, this method accepts a `$pid`, and also works outside a project context when a `pid` GET parameter is set.
getRepeatingForms([$event_id, $pid])) | `?.?.?`<br>`2` | Returns an array of repeating form names for the current or specified event & pid.
getSafePath($path[, $root])) | `?.?.?`<br>`2` | Checks a file path to make sure a [path traversal attack](https://www.owasp.org/index.php/Path_Traversal) is not in progress and returns a normalized path similar to PHP's `realpath()` function.  If a path traversal attack is detected, an exception is thrown.  This is very import when generating paths using strings created from user input.  The `$path` can be relative to the `$root`, or include it.  If `$root` is not specified, the module directory is assumed.  The `$root` can be either absolute or relative to the module directory.  A path traversal attack is considered to be in progress if the the `$root` does not contain the `$path`.
getSettingConfig($key)) | `?.?.?`<br>`1` | Returns the configuration for the specified setting.
getSettingKeyPrefix()) | `?.?.?`<br>`1` | This method can be overridden to prefix all setting keys.  This allows for multiple versions of settings depending on contexts defined by the module.
**DEPRECATED** getSQLInClause($columnName, $values)) | `?.?.?`<br>`1` | This method will be removed soon in favor of prepared statements.  Generates SQL to determine if the given column is in the given array of values.  Checking for `NULL` rows is supported as well by including the string `'NULL'` in the array of values.
getSubSettings($key&nbsp;[,&nbsp;$pid])) | `?.?.?`<br>`1` | Returns the sub-settings under the specified key in a user friendly array format.  In most cases the project id can be detected automatically, but it can optionally be specified as a parameter instead.
getUrl($path [, $noAuth=false [, $useApiEndpoint=false]])) | `?.?.?`<br>`1` | Get the url to a resource (php page, js/css file, image etc.) at the specified path relative to the module directory. A `$module` variable representing an instance of your module class will automatically be available in PHP files.  If the $noAuth parameter is set to true, then "&NOAUTH" will be appended to the URL, which disables REDCap's authentication for that PHP page (assuming the link's URL in config.json contains "?NOAUTH"). Also, if you wish to obtain an alternative form of the URL that does not contain the REDCap version directory (e.g., https://example.com/redcap/redcap_vX.X.X/ExternalModules/?prefix=your_module&page=index&pid=33), then set $useApiEndpoint=true, which will return a version-less URL using the API end-point (e.g., https://example.com/redcap/api/?prefix=your_module&page=index&pid=33). Both links will work identically.
getUser([$username])) | `?.?.?`<br>`2` | Returns a `User` object for the given username, or the current user if no username is specified.  This `User` object is documented below.
getUserSetting($key)) | `?.?.?`<br>`1` | Returns the value stored for the specified key for the current user and project.  Null is always returned on surveys and NOAUTH pages.
hasPermission($permissionName)) | `?.?.?`<br>`1` | checks whether the current External Module has permission for $permissionName
importDataDictionary($project_id,$path)) | `?.?.?`<br>`2` | Given a project id and a path, imports a data dictionary CSV file.
initializeJavascriptModuleObject()) | `?.?.?`<br>`1` | Returns a JavaScript block that initializes the JavaScript version of the module object (documented below).
isPage($path)) | `?.?.?`<br>`2` | Returns true if the current page matches the supplied file/dir path.  The path can be any file/dir under the versioned REDCap directory (ex: `Design/online_designer.php`).
isRoute($routeName)) | `?.?.?`<br>`2` | Returns true if the 'route' GET/URL parameter matches the specified string.
isSurveyPage()) | `?.?.?`<br>`1` | Returns true if the current page is a survey.  This is primarily useful in the **redcap_every_page_before_render** and **redcap_save_record** hooks.
log($message[, $parameters])) | `?.?.?`<br>`1` | Inserts a log entry including a message and optional array of key-value pairs for later retrieval using the **queryLogs()** method, and returns the inserted **log_id**.  Some parameters/columns are stored automatically, even if the **$parameters** argument is omitted (see **queryLogs()** for more details).  Log parameter names are only allowed to contain alphanumeric, space, dash, underscore, or dollar sign characters.
query($sql)) | `?.?.?`<br>`1` | A thin wrapper around REDCap's db_query() that includes automatic error detection and reporting. 
queryLogs($sql)) | `?.?.?`<br>`1` | Queries log entries added via the **log()** method using SQL-like syntax with the "from" portion omitted, and returns a MySQL result resource (just like **mysql_query()**).  Queries can include standard "select", "where", "order by", and "group by" clauses.  Available columns include **log_id**, **timestamp**, **user**, **ip**, **project_id**, **record**, **message**, and any parameter name passed to the **log()** method.  All columns must be specified explicitly ("select \*" syntax is not supported).  The raw SQL being executed by this method can be retrieved by calling **getQueryLogsSql()**.  Here are some query examples:*<br><br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;select timestamp, user where message = 'some message'<br><br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;select message, ip<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;where<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;timestamp > '2017-07-07'<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;and user in ('joe', 'tom')<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;or some_parameter like '%abc%'<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;order by timestamp desc*<br><br>If the `external_module_id` or `project_id` columns are not specified in the where clause, queries are limited to the current module and project (if detected) by default.  For complex queries, the log table can be manually queried (this method does not have to be used). 
records->lock($recordIds)) | `?.?.?`<br>`2` | Locks all forms/instances for the given record ids.
removeLogs($sql)) | `?.?.?`<br>`1` | Removes log entries matching the current module, current project (if detected), and the specified sql "where" clause.
removeProjectSetting($key&nbsp;[,&nbsp;$pid])) | `?.?.?`<br>`1` | Remove the value stored for this project and the specified key.  In most cases the project id can be detected automatically, but it can optionaly be specified as a parameter instead. 
removeSystemSetting($key)) | `?.?.?`<br>`1` | Removes the value stored systemwide for the specified key.
removeUserSetting($key)) | `?.?.?`<br>`1` | Removes the value stored for the specified key for the current user and project.  This method does nothing on surveys and NOAUTH pages.
renameDAG($dagId, $name)) | `?.?.?`<br>`1` | Renames the DAG with the given ID to the specified name.
requireInteger($mixed)) | `?.?.?`<br>`2` | Throws an exception if the supplied value is not an integer or a string representation of an integer.  Returns the integer equivalent of the given value regardless.
resetSurveyAndGetCodes(<br>&emsp;$projectId, $recordId<br>&emsp;[, $surveyFormName, $eventId]<br>)) | `?.?.?`<br>`1` | Resets the survey status so that REDCap will allow the survey to be accessed again (completed surveys can't be edited again without changing the survey settings).  A survey participant and respondent are also created if they doesn't exist.
saveFile($filePath[, $pid])) | `?.?.?`<br>`1` | Saves a file and returns the new edoc id.
setDAG($record, $dagId)) | `?.?.?`<br>`1` | Sets the DAG for the given record ID to given DAG ID.
setData($record, $fieldName, $values)) | `?.?.?`<br>`1` | Sets the data for the given record and field name to the specified value or array of values.
setProjectSetting($key,&nbsp;$value&nbsp;[,&nbsp;$pid])) | `?.?.?`<br>`1` | Sets the setting specified by the key to the specified value for this project.  In most cases the project id can be detected automatically, but it can optionally be specified as a parameter instead.
setProjectSettings($settings[, $pid])) | `?.?.?`<br>`1` | Saves all project settings (to be used with getProjectSettings).  Useful for cases when you may create a custom config page or need to overwrite all project settings for an external module.
setSystemSetting($key,&nbsp;$value)) | `?.?.?`<br>`1` | Set the setting specified by the key to the specified value systemwide (shared by all projects).
setUserSetting($key, $value)) | `?.?.?`<br>`1` |  Sets the setting specified by the key to the given value for the current user and project.  This method does nothing on surveys and NOAUTH pages.  
tt($key[, $value, ...])) | `?.?.?`<br>`2` | Returns the language string identified by `$key`, optionally interpolated using the values supplied as further arguments (if the first value argument is an array, its elements will be used for interpolation and any further arguments ignored). Refer to the [internationalization guide](../i18n-guide.md) for more details.
tt_addToJavascriptModuleObject(<br>&emsp;$key, $value<br>)) | `?.?.?`<br>`2` | Adds a key/value pair to the language store for use in the _JavaScript Module Object_.
tt_transferToJavascriptModuleObject(<br>&emsp;[$key[, $value[, ...]]]<br>)) | `?.?.?`<br>`2` | Transfers one (interpolated) or many language strings (without interpolation) to the _JavaScript Module Object_. When no arguments are passed, or `null` for `$key`, all strings defined in the module's language file are transferred. An array of keys can be passed to transfer multiple language strings. When `$key` is a string, further arguments can be passed which will be used for interpolation (if the first such argument is an array, its elements will be used for interpolation and any further arguments ignored).
validateSettings($settings)) | `?.?.?`<br>`1` | Override this method in order to validate settings at save time.  If a non-empty error message string is returned, it will be displayed to the user, and settings will NOT be saved.


#### Project Methods
The following methods are avaiable on the `Project` object returned by `$module->framework->getProject()`.

Method  | Description
------- | -----------
getUsers()) | `?.?.?`<br>`2` | Returns an array of `User` objects for each user with rights on the project.

#### User Methods
The following methods are avaiable on the `User` object returned by `$module->framework->getUser()`.

Method  | Description
------- | -----------
getEmail()) | `?.?.?`<br>`2` | Returns the user's primary email address.
getRights([$project_ids])) | `?.?.?`<br>`2` | Returns this user's rights on the specified project id(s).  If a single project id is specified, the rights for that project are returned.  If multiple project ids are specified, an array is returned with project id indexes pointing to rights arrays.  If no project ids are specified, rights for the current project are returned.
hasDesignRights([$project_id])) | `?.?.?`<br>`2` | Returns true if the user has design rights on the specified project.  The current project is used if no project id is specified.
isSuperUser()) | `?.?.?`<br>`2` | Returns true if the user is a super user.

#### JavaScript Module Object
A JavaScript version of any module object can be initialized by including the JavaScript code block returned by the PHP module object's `initializeJavascriptModuleObject()` method at any point in any hook. The name of the _JavaScript Module Object_ is returned by the framework method `getJavascriptModuleObjectName()`. Here is a basic example of how to initialize and use the _JavaScript Module Object_ from any PHP hook:

```php
<?=$this->initializeJavascriptModuleObject()?>

<script>
	$(function(){
        var module = <?=$this->framework->getJavascriptModuleObjectName()?>;
		module.log('Hello from JavaScript!');
	})
</script>
```

The _JavaScript Module Object_ provides the following methods framework version 2 and up:

Method  | Description
------- | -----------
getUrlParameter(name) | Returns the value for the specified GET/URL parameter.
getUrlParameters() | Returns an object containing all GET parameters for the current URL.
isImportPage() | Returns true if the current page is a **Data Import Tool** page.
isImportReviewPage() | Returns true if the current page is the **Data Import Tool** review page.
isImportSuccessPage() | Returns true if the current page is the **Data Import Tool** success page.
isRoute(routeName) | See the description for the PHP version of this method (above). 
log(message[, parameters]) | See the description for the PHP version of this method (above).
tt(key[, value[, ...]]) | Returns the string identified by `key` from the language store, optionally interpolated with the values passed as additional arguments (if the first such value is an array or object, its elements/members are used for interpolation and any further arguments are ignored). Refer to the [internationalization guide](../i18n-guide.md) for more details.
tt_add(key, value) | Adds a (new) key/value pair to the language store of the _JavaScript Module Object_. If an entry with the same name already exists in the store, it will be overwritten.
