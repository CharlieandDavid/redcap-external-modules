## External Module Framework - Versioning Introduction
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

|Framework Versions|
|------- |
|[Version 2](v2.md)|
|[Version 1](v1.md)|
