## External Module Framework Versioning

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

|Framework Version |First Standard Release|First LTS Release|
|----------------- |------|-----|
|[Version 6](v6.md)|10.4.1|TBD   |
|[Version 5](v5.md)|9.10.0|10.0.5|
|[Version 4](v4.md)|9.7.8 |10.0.5|
|[Version 3](v3.md)|9.1.1 |9.1.3|
|[Version 2](v2.md)|8.11.6|9.1.3|
|[Version 1](v1.md)|8.0.0 |8.1.2|

<br/>

#### Methods Provided by the Framework

Method documentation has moved [here](../methods.md).