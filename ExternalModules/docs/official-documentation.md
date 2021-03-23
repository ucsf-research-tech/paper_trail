## External Module Framework - Official Documentation

**[Click here](methods.md) for method documentation.**

"External Modules" is a class-based framework for plugins and hooks in REDCap. Modules can utilize any of the "REDCap" class methods (e.g., \REDCap::getData), and they also come with many other helpful built-in methods to store and manage settings for a given module, as well as provide support for internationalization (translation of displayed strings) of modules. The documentation provided on this page will be useful for anyone creating an external module.

If you have created a module and wish to share it with the REDCap community, you may submit it to the [REDCap External Modules Submission Survey](https://redcap.vanderbilt.edu/surveys/?s=X83KEHJ7EA). If your module gets approved after submission, it will become available for download by any REDCap administrator from the [REDCap Repo](https://redcap.vanderbilt.edu/consortium/modules/).

### Naming a module

Modules must follow a specific naming scheme for the module directory that will sit on the REDCap web server. Each version of a module will have its own directory (like REDCap) and will be located in the `/redcap/modules/` directory on the server. A module directory name consists of three parts: 
1. A **unique name** (so that it will not duplicate any one else's module in the consortium) in [snake case](https://en.wikipedia.org/wiki/Snake_case) format
1. "_v" (an underscore followed by the letter "v")
1. A **module version number**.  [Semantic Versioning](https://semver.org/) is recommended (ex: `1.2.3`), although simpler `#.#` versioning is also supported (ex: `1.2`).

The diagram below shows the general directory structure of some hypothetical  modules to illustrate how modules will sit on the REDCap web server alongside other REDCap files and directories.

```
redcap
|-- modules
|   |-- my_module_name_v1.0.0
|   |-- other_module_v2.9
|   |-- other_module_v2.10
|   |-- other_module_v2.11
|   |-- yet_another_module_v1.5.3
|-- redcap_vX.X.X
|-- redcap_connect.php
|-- ...
```

### Renaming a module

The display name for a module can be safely renamed at any time by updating the `name` in `config.json` (as documented later).  The module directory name on the system may also be changed at any time.  Module specific URLs changing is typically the only side effect, but directory renames should still be tested in a non-production environment first to make sure all module features still work as expected.  To rename a module directory, follow these steps:
1. Deploy the module (to all web nodes if there are multiple) under the new directory name (prefix) with a version suffix that matches the version currently enabled on the system.
    - This new deployment can contain code changes as well (ex: renaming the module in `config.json`, renaming the module's main class, etc.)
1. Run the following query:
    ```
    update redcap_external_modules
    set directory_prefix = 'old_directory_prefix'
    where directory_prefix = 'new_directory_prefix'
    ```
1. Test to make sure the module still functions as expected.
    - All project enables, settings, logs, crons, etc. should be preserved.
1. Once enough time has passed for any running cron jobs to finish, delete the old directory (on all web nodes if there are multiple)


### Module requirements

**Every module must have two files at the minimum:** 1) the module's PHP class file, in which the file name will be different for every module (e.g., `MyModuleClass.php`), and 2) a configuration file (`config.json`). The config file must be in JSON format, and must always be explictly named "*config.json*". The config file will contain all the module's basic configuration, such as its title, author information, module permissions, and many other module settings. The module class file houses the basic business logic of the module, and it can be named whatever you like so long as the file name matches the class name (e.g., Votecap.php contains the class Votecap).

#### 1) Module class

Each module must define a module class that extends `ExternalModules\AbstractExternalModule` (see the example below).  Your module class is the central PHP file that will run all the business logic for the module. You may have many other PHP files (classes or include files), as well as JavaScript, CSS, etc. All other such files are optional, but the module class itself is necessary and drives the module.

```php
<?php
// Set the namespace defined in your config file
namespace MyModuleNamespace\MyModuleClass;

// Declare your module class, which must extend AbstractExternalModule 
class MyModuleClass extends \ExternalModules\AbstractExternalModule {
     // Your module methods, constants, etc. go here
}
```

A module's class name can be named whatever you wish. The module class file must also have the same name as the class name (e.g., **MyModuleClass.php** containing the class **MyModuleClass**). Also, the namespace is up to you to name. Please note that the full namespace declared in a module must exactly match the "namespace" setting in the **config.json** file (with the exception of there being a double backslash in the config file because of escaping in JSON). For example, while the module class may have `namespace MyModuleNamespace\MyModuleClass;`, the config file will have `"namespace": "MyModuleNamespace\\MyModuleClass"`.

#### 2) Configuration file

The file `config.json` provides all the basic configuration information for the module in JSON format. At the minimum, the config file must have the following defined: **name, namespace, description, and authors**. `name` is the module title, and `description` is the longer description of the module (typically between one sentence and a whole paragraph in length), both of which are displayed in the module list on the module manager page. Regarding `authors`, if there are several contributors to the module, you can provide multiple authors whose names get displayed below the module description on the Control Center's module manager page.

The PHP `namespace` of your module class must also be specified in the config file, and it must have a sub-namespace. Thus the overall namespace consists of two parts. The first part is the main namespace, and the second part is the sub-namespace. **It is required that the sub-namespace matches the module's class name (e.g., MyModuleClass).** The first part of the namespace can be any name you want, but you might want to use the name of your institution as a way of grouping the modules that you and your team create (e.g., `namespace Vanderbilt\VoteCap;`). That's only a suggestion though. Using namespacing with sub-namespacing in this particular way helps prevent against collisions in PHP class naming when multiple modules are being used in REDCap at the same time. 

Example of the minimum requirements of the configuration file:

``` json
{
   "name": "Example Module",
   "namespace": "MyModuleNamespace\\MyModuleClass", 
   "description": "This is a description of the module, and will be displayed below the module name in the user interface.",
   "authors": [
       {
            "name": "Jon Snow",
            "email": "jon.snow@vumc.org",
            "institution": "Vanderbilt University Medical Center"
        }
    ]
}
```

Below is a *mostly* comprehensive list of all items that can be added to **config.json**. Remember that all items in the file must be in JSON format, which includes making sure that quotes and other characters get escaped properly. **An extensive example of config.json is provided at the very bottom of this page** if you wish to see how all these items will be structured.

* Module **name**
* Module  **description**
* **documentation** can be used to provide a filename or URL for the "View Documentation" link in the module list.  If this setting is omitted, the first filename that starts with "README" will be used if it exists.  If a markdown file is used, it will be automatically rendered as HTML.
* For module **authors**, enter their **name**,  **email**, and **institution**. At least one author is required to run the module.
* Grant **permissions** for all of the operations, including hooks (e.g., **redcap_save_record**).
* The **framework-version** version used by the module ([click here](framework/intro.md) for details).
* **links** specify any links to show up on the left-hand toolbar. These include stand-alone webpages (substitutes for plugins) or links to outside websites. These are listable at the control-center level or at the project level.  Link URLs and names can be modified before display with the `redcap_module_link_check_display` hook.  A **link** consists of:
	* A **name** to be displayed on the site
   * A **key** (unique within _links_) to identify the link (optional, limited to [-a-zA-Z0-9]). The key (prefixed with the module's prefix and a dash) will be output in the 'data-link-key' attribute of the rendered a tag.
	* An **icon**
		* For framework version 3 and later, the **icon** must either be the [Font Awesome](https://fontawesome.com/icons?d=gallery) classes (ex: `fas fa-user-friends`) or a path to an icon file within the module itself (ex: `images/my-icon.png`).
		* For framework versions prior to 3, the filename of a REDCap icon in the `Resources/images` folder must be specified without the extension (ex: `user_list`).  This is deprecated because those icons are no longer used by REDCap itself, and may be modified or removed at any time.
	* A **url** either in the local directory or external to REDCap. External links need to start with either 'http://' or 'https://'. Javascript links are also supported; these need to start with 'javascript:' and may only use single quotes.
   * A **target** that will be used for the 'target' attribute of the rendered a tag.
* **system-settings** specify settings configurable at the system-wide level (this Control Center).  Settings do NOT have to be defined in config.json to be used programmatically.  
* **project-settings** specify settings configurable at the project level, different for each project.  Settings do NOT have to be defined in config.json to be used programatically.  
* A setting consists of:
	* A **key** that is the unique identifier for the item. Dashes (-'s) are preferred to underscores (_'s).
	* A **name** that is the plain-text label for the identifier. You have to tell your users what they are filling out.
	* **required** is a boolean to specify whether the user has to fill this item out in order to use the module.
	* **type** is the data type. Available options are: 
		* text
		* textarea
		* descriptive
		* json
		* rich-text
		* field-list
		* user-role-list
		* user-list
		* dag-list
		* dropdown
		* checkbox
		* button
		* project-id
		* form-list
		* event-list
		* color-picker
			* This option is backward compatible with older versions of REDCap where it will appear as a text field into which an HTML color can be entered.  
		* sub_settings
		* radio
		* file
		* date
			* Date fields currently use jQuery UI's datepicker and include validation to ensure that dates entered follows datepicker's default date format (MM/DD/YYYY).  This could be expanded to include other date formats in the future.
		* email
			* Includes validation to ensure that the value specified is a valid email address.
	* **choices** consist of a **value** and a **name** for selecting elements (dropdowns, radios).
	* **super-users-only** can be set to **true** to only allow super users to access a given setting.
	* **repeatable** is a boolean that specifies whether the element can repeat many times. **If it is repeatable (true), the element will return an array of values.**
	* **autocomplete** is a boolean that enables autocomplete on dropdown fields.
	* **branchingLogic** is an structure which represents a condition or a set of conditions that defines whether the field should be displayed. See examples at the end of this section.
	  * **WARNING:** There are known issues with sub-settings and `branchingLogic` currently.  If anyone would like to help resolve them, the best course of action might be to help [grezniczek](https://github.com/grezniczek) complete his [new configuration interface](https://github.com/grezniczek/redcap_em_config_study), which already has an imrpoved implementation of sub-setting branching logic.
    * **hidden** is a boolean that when present and set to a [truthy value](https://www.php.net/manual/en/types.comparisons.php) in a _top level_ setting (system or project) will not display this setting in the settings dialog. For example, true, "true", 1 and "1" are all truthy, while false, 0, "0", and "" are falsy. Note that "false" as a non-empty string is truthy!
	* When type = **sub_settings**, the sub_settings element can specify a group of items that can be repeated as a group if the sub_settings itself is repeatable. The settings within sub_settings follow the same specification here.  It is also possible to nest sub_settings within sub_settings.
	* As a reminder, true and false are specified as their actual values (true/false not as the strings "true"/"false"). Other than that, all values and variables are strings.
	* **DEPRECATED (for now): Default values do NOT currently work consistently, and will likely need to be re-implemented.** Both project-settings and system-settings may have a **default** value provided (using the attribute "default"). This will set the value of a setting when the module is enabled either in the project or system, respectively.
* To support **internationalization** of External Modules (translatability of strings displayed by modules), many of the JSON keys in the configuration file have a _companion key_ that is prepended by "**tt_**", such as *tt_name* or *tt_description* (full list of translatable keys: _name_, _description_, _documentation_, _icon_, _url_, _default_, _cron_description_, as well as _required_ and _hidden_). When provided with a value that corresponds to a key in a language file supplied with the module, the value for the setting will be replaced with the value from the language file. For details, please refer to the [internationalization guide](i18n-guide.md).
* **Attention!** If your JSON is not properly specified, an Exception will be thrown.

#### Examples of branching logic

A basic case.

``` json
"branchingLogic": {
    "field": "source1",
    "value": "123"
}
```

Specifying a comparison operator (valid operators: "=", "<", "<=", ">", ">=", ">", "<>").

``` json
"branchingLogic": {
    "field": "source1",
    "op": "<",
    "value": "123"
}
```

Multiple conditions.

``` json
"branchingLogic": {
    "conditions": [
        {
            "field": "source1",
            "value": "123"
        },
        {
            "field": "source2",
            "op": "<>",
            "value": ""
        }
    ]
}
```

Multiple conditions - "or" clause.

``` json
"branchingLogic": {
    "type": "or",
    "conditions": [
        {
            "field": "source1",
            "op": "<=",
            "value": "123"
        },
        {
            "field": "source2",
            "op": ">=",
            "value": "123"
        }
    ]
}
```

Obs.: when `op` is not defined, "=" is assumed. When `type` is not defined, "and" is assumed.


### How to call REDCap Hooks

One of the more powerful things that modules can do is to utilize REDCap Hooks, which allow you to execute PHP code in specific places in REDCap. For general information on REDCap hook functions, see the hook documentation. **Before you can utilize a hook in your module, you must explicitly set permissions for the hook in your config.json file**, as seen in the example below. Simply provide the hook function name in the "permissions" array in the config file.

``` json
{
   "permissions": [
	"redcap_project_home_page",
	"redcap_control_center"
   ]
}
```

Next, you must **name a method in your module class the exact same name as the name of the hook function**. For example, in the HideHomePageEmails class below, there is a method named `redcap_project_home_page`, which means that when REDCap calls the redcap_project_home_page hook, it will execute the module's redcap_project_home_page method.

``` php
<?php 
namespace Vanderbilt\HideHomePageEmails;

class HideHomePageEmails extends \ExternalModules\AbstractExternalModule 
{
    // This method will be called by the redcap_data_entry_form hook
    function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance) 
    {
	// Put your code here to get executed by the hook
    }
}
```

Remember that each hook function has different method parameters that get passed to it (e.g., $project_id), so be sure to include the correct parameters as seen in the hook documentation for the particular hook function you are defining in your module class.

##### Special note regarding the `redcap_email` hook
When used in an External Module, this hook **must** return an actual boolean value (either `true` or `false`). Do not return 0, 1, or other truthy/falsy values. The results of multiple modules using this hook will be combined with logical AND, i.e. as long as one implementation returns `false`, the email will not be sent by REDCap.

##### Every Page Hooks
By default, every page hooks will only execute on project specific pages (and only on projects with the module enabled).  However, you can allow them to execute on pages that aren't project specific by setting the following flag in `config.json`.  **WARNING: This flag is risky and should ONLY be used if absolutely necessary.  It will cause your every page hooks to fire on literally ALL non-project pages (the login page, control center pages, "My Projects", etc.).  You will need strict and well tested checking at the top of your hook to make sure it only executes in exactly the contexts desired:**

`"enable-every-page-hooks-on-system-pages": true`

##### Extra hooks provided by External Modules
There are a few extra hooks dedicated for modules use:

- `redcap_module_configuration_settings($project_id, $settings)`: Triggered when the system or project configuration dialog is displayed for a given module.  This hook allows modules to dynamically modify and return the settings that will be displayed.
- `redcap_module_system_enable($version)`: Triggered when a module gets enabled on Control Center.
- `redcap_module_system_disable($version)`: Triggered when a module gets disabled on Control Center.
- `redcap_module_system_change_version($version, $old_version)`: Triggered when a module version is changed.
- `redcap_module_project_enable($version, $project_id)`: Triggered when a module gets enabled on a specific project.
- `redcap_module_project_disable($version, $project_id)`: Triggered when a module gets disabled on a specific project.
- `redcap_module_configure_button_display($project_id)`: Triggered when each enabled module defined is rendered.  Return `null` if you don't want to display the Configure button and `true` to display.
- `redcap_module_link_check_display($project_id, $link)`: Triggered when each link defined in config.json is rendered.  Override this method and return `null` if you don't want to display the link, or modify and return the `$link` parameter as desired. `$link` is an array matching the values of the link from config.json. The 'url' value will already have the module prefix and page appended as GET parameters.  This method also controls whether pages will load if users access their URLs directly.
- `redcap_module_save_configuration($project_id)`: Triggered after a module configuration is saved.
- `redcap_module_import_page_top($project_id)`: Triggered at the top of the Data Import Tool page.

Examples:

``` php
<?php

function redcap_module_system_enable($version) {
    // Do stuff, e.g. create DB table.
}

function redcap_module_system_change_version($version, $old_version) {
    if ($version == 'v2.0') {
        // Do stuff, e.g. update DB table.
    }
}

function redcap_module_system_disable($version) {
    // Do stuff, e.g. delete DB table.
}
```

### How to create plugin pages for your module

A module can have plugin pages (or what resemble traditional REDCap plugins). They are called "plugin" pages because they exist as a new page (i.e., does not currently exist in REDCap), whereas a hook runs in-line inside of an existing REDCap page/request. 

The difference between module plugin pages and traditional plugins is that while you would typically navigate directly to a traditional plugin's URL in a web browser (e.g., https://example.com/redcap/plugins/votecap/pluginfile.php?pid=26), module plugins cannot be accessed directly but can only be accessed through the External Modules framework's directory (e.g., https://example.com/redcap/redcap_vX.X.X/ExternalModules/?prefix=your_module&page=pluginfile&pid=26). Thus it is important to note that PHP files in a module's directory (e.g., /redcap/modules/votecap/pluginfile.php) cannot be accessed directly from the web browser.

Note: If you are building links to plugin pages in your module, you should use the  `getUrl()` method (documented in the methods list below), which will build the URL all the required parameters for you.

**Add a link on the project menu to your plugin:** Adding a page to your module is fairly easy. First, it requires adding an item to the `links` option in the config.json file. In order for the plugin link to show up in a project where the module is enabled, put the link settings (name, icon, and url) under the `project` sub-option, as seen below, in which *url* notes that index.php in the module directory will be the endpoint of the URL, *"VoteCap"* will be the link text displayed. See the **Config.json** section above for details on the *icon* parameter. You may add as many links as you wish.  By default, project links will only display for superusers and users with design rights, but this can be customized in each module (see the *redcap_module_link_check_display()* documentation above). 

``` json
{
   "links": {
      "project": [
         {
            "name": "VoteCap",
            "key": "votecap",
            "icon": "fas fa-receipt",
            "url": "index.php",
            "show-header-and-footer": true
         }
      ]
   }
}
```

The following optional settings may also be specified for each project link:

Setting&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; | Description
------- | -----------
show-header-and-footer | Specify **true** to automatically show the REDCap header and footer on this page.  Defaults to **false** when omitted.

**Adding links to the Control Center menu:**
If you want to similarly add links to your plugins on the Control Center's left-hand menu (as opposed to a project's left-hand menu), then you will need to add a `control-center` section to your `links` settings, as seen below.

``` json
{
   "links": {
      "project": [
         {
            "name": "VoteCap",
            "key": "votecap",
            "icon": "fas fa-receipt",
            "url": "index.php"
         }
      ],
      "control-center": [
         {
            "name": "VoteCap System Config",
            "key": "config",
            "icon": "fas fa-receipt",
            "url": "config.php"
         }
      ]
   }
}
```

**Disabling authentication in plugins:** If a plugin page should not enforce REDCap's authentication but instead should be publicly viewable to the web, then in the config.json file you need to 1) **append `?NOAUTH` to the URL in the `links` setting**, and then 2) **add the plugin file name to the `no-auth-pages` setting**, as seen below. Once those are set, all URLs built using `getUrl()` will automatically append *NOAUTH* to the plugin URL, and when someone accesses the plugin page, it will know not to enforce authentication because of the *no-auth-pages* setting. Otherwise, External Modules will enforce REDCap authentication by default.

``` json
{
   "links": {
      "project": [
         {
            "name": "VoteCap",
            "key": "votecap",
            "icon": "fas fa-receipt",
            "url": "index.php?NOAUTH"
         }
      ]
   },
   "no-auth-pages": [
      "index"
   ],
}
```

The actual code of your plugin page will likely reference methods in your module class. It is common to first initiate the plugin by instantiating your module class and/or calling a method in the module class, in which this will cause the External Modules framework to process the parameters passed, discern if authentication is required, and other initial things that will be required before processing the plugin and outputting any response HTML (if any) to the browser.

**Example plugin page code:**

```php
<?php
// A $module variable will automatically be available and set to an instance of your module.
// It can be used like so:
$value = $module->getProjectSetting('my-project-setting');
// More things to do here, if you wish
```

### Available developer methods in External Modules

The External Modules framework provides objects representing a module, both in **PHP** and **JavaScript**.

The publicly supported methods that module creators may utilize depend on the framework version they opt into via the configuration file and are documented [here](methods.md).

**Attention!** Modules should _not_ reference any other methods or files that exist in the External Modules framework (like the *ExternalModules* class) as they could change at any time. If a method you believe should be supported by these module objects is missing, please feel free to request it via an [issue](https://github.com/vanderbilt/redcap-external-modules/issues) or [pull request](https://github.com/vanderbilt/redcap-external-modules/pulls) on the framework's GitHub page.

### Utilizing Cron Jobs for Modules

Modules can actually have their own cron jobs that are run at a given interval by REDCap (alongside REDCap's internal cron jobs). This allows modules to have processes that are not run in real time but are run in the background at a given interval. There is no limit on the number of cron jobs that a module can have, and each can be configured to run at different times for different purposes. 

Crons are registered when a module is enabled or updated.  If a cron is added without updating a module's version, you will need to disable then re-enable that module to register the cron.

Module cron jobs must be defined in `config.json` as seen below, in which each has a `cron_name` (alphanumeric name that is unique within the module), a `cron_description` (text that describes what the cron does), and a `method` (refers to a PHP method in the module class that will be executed when the cron is run). The `cron_frequency` and `cron_max_run_time` must be defined as integers (in units of seconds). The cron_max_run_time refers to the maximum time that the cron job is expected to run (once that time is passed, if the cron is still listed in the state of "processing", it assumes it has failed/crashed and thus will automatically enable it to run again at the next scheduled interval).  Here is an example cron method definition:
```
/**
 * @param array $cronAttributes A copy of the cron's configuration block from config.json.
 */
function myCronMethodName($cronAttributes){
    // ...
}
```

A `cron_frequency` and a `cron_max_run_time` can be specified, --OR-- an `cron_hour` and a `cron_minute` can be specified, but not both. In addition to an cron_hour and a cron_minute, a `cron_weekday` (0 [Sundays] - 6 [Saturdays]) or a `cron_monthday` (day of the month) can be specified.

Note: If any of the cron attributes (including cron_frequency/cron_max_run_time or cron_hour/cron_minute, but not both) are missing, it will prevent the module from being enabled.

#### Setting a Safe Maximum Run Time

*The following does not apply to crons configured using `cron_hour` and `cron_minute`, which instead use a database flag to prevent concurrency.*

The `cron_max_run_time` is the amount of time that REDCap will wait for a cron that runs longer than it's `cron_frequency` to finish before starting another instance of the same cron.  If a cron runs longer than it's `cron_max_run_time`, REDCap will assume it has either crashed or been killed, and will allow a new instance of the same cron to start.  If set too low, multiple crons could run at the same time and cause either the module or the entire server to crash.  It is recommended to set a `cron_max_run_time` larger than the longest amount of time a cron could possibly run in a near worst case scenario.

For example, let's say we have a cron that runs once a minute (a `cron_frequency` value of `60` seconds) and normally takes 30 seconds to finish.  Consider the following scenarios:
- If the amount of data processed could increase and cause this cron to take 90 seconds to finish, any `cron_max_run_time` less than 90 seconds would be unsafe.  Even if concurrent crons are not problematic for the module itself, this could cause the number active cron processes to pile up over time and crash the server.
- If the amount of data processed could increase and cause this cron to occasionally take hours to finish, it may be prudent to set much larger `cron_max_run_time` to be safe (perhaps 24 hours, or `86400` seconds).

#### Setting a Project Context Within a Cron
Using methods like `$module->getProjectId()` will not work by default inside a cron because crons do not run in a project context.  Here is one common way of simulating a project context in a cron method:
```
function cron($cronInfo){
	$originalPid = $_GET['pid'];

	foreach($this->framework->getProjectsWithModuleEnabled() as $localProjectId){
		$_GET['pid'] = $localProjectId;

		// Project specific method calls go here.
	}

	// Put the pid back the way it was before this cron job (likely doesn't matter, but is good housekeeping practice)
	$_GET['pid'] = $originalPid;

	return "The \"{$cronInfo['cron_description']}\" cron job completed successfully.";
}
```

#### Cron Configuration Examples

``` json
{
   "crons": [
      {
         "cron_name": "cron1",
         "cron_description": "Cron that runs every 30 minutes to do X",
         "method": "cron1",
         "cron_frequency": "1800",
         "cron_max_run_time": "86400"
      },
      {
         "cron_name": "cron2",
         "cron_description": "Cron that runs daily to do YY",
         "method": "some_other_method",
         "cron_frequency": "86400",
         "cron_max_run_time": "172800"
      },
      {
         "cron_name": "cron3",
         "cron_description": "Cron that runs daily at 1:15 am to do YYY",
         "method": "some_other_method_3",
         "cron_hour": 1,
         "cron_minute": 15
      },
      {
         "cron_name": "cron4",
         "cron_description": "Cron that runs on Mondays at 2:25 pm to do YYYY",
         "method": "some_other_method_4",
         "cron_hour": 14,
         "cron_minute": 25,
         "cron_weekday": 1
      },
      {
         "cron_name": "cron5",
         "cron_description": "Cron that runs on the second of each month at 4:30 pm to do YYYYY",
         "method": "some_other_method_5",
         "cron_hour": 16,
         "cron_minute": 30,
         "cron_monthday": 2
      }
   ]
}
```

### Module compatibility with specific versions of REDCap and PHP

It may be the case that a module is not compatible with specific versions of REDCap or specific versions of PHP. In this case, the `compatibility` option can be set in the config.json file using any or all of the four options seen below. (If any are listed in the config file but left blank as "", they will just be ignored.) Each of these are optional and should only be used when it is known that the module is not compatible with specific versions of PHP or REDCap. You may provide PHP min or max version as well as the REDCap min or max version with which your module is compatible. If a module is downloaded and enabled, these settings will be checked during the module enabling process, and if they do not comply with the current REDCap version and PHP version of the server where it is being installed, then REDCap will not be allow the module to be enabled.

```JSON
{	
   "compatibility": {
      "php-version-min": "5.4.0",
      "php-version-max": "5.6.2",
      "redcap-version-min": "7.0.0",
      "redcap-version-max": ""
   }
}
```

### JavaScript recommendations

If your module will be using JavaScript, it is *highly recommended* that your JavaScript variables and functions not be placed in the global scope. Doing so could cause a conflict with other modules that are running at the same time that might have the same variable/function names. As an alternative, consider creating a function as an **IIFE (Immediately Invoked Function Expression)** or instead creating the variables/functions as properties of a **single global scope object** for the module, as seen below.

```JavaScript
<script type="text/javascript">
  // IIFE - Immediately Invoked Function Expression
  (function($, window, document) {
      // The $ is now locally scoped

      // The rest of your code goes here!

  }(window.jQuery, window, document));
  // The global jQuery object is passed as a parameter
</script>
```

```JavaScript
<script type="text/javascript">
  // Single global scope object containing all variables/functions
  var MCRI_SurveyLinkLookup = {};
  MCRI_SurveyLinkLookup.modulevar = "Hello world!";
  MCRI_SurveyLinkLookup.sayIt = function() {
    alert(this.modulevar);
  };
  MCRI_SurveyLinkLookup.sayIt();
</script>
```

### Other useful things to know

If the module class contains the __construct() method, you **must** be sure to call `parent::__construct();` as the first thing in the method, as seen below.

```php
class MyModuleClass extends AbstractExternalModule {
   public function __construct()
   {
      parent::__construct();
      // Other code to run when object is instantiated
   }
}
```

### Including Dependencies/Libraries in your Module

If your module uses a third party library (i.e. PHPMailer) that is available in the [packagist.org](https://packagist.org) repo, please use [composer](https://getcomposer.org/) to include it.  While this adds an extra step when submitting to the module repo, it greatly reduces the chances of conflicts between modules that can cause those modules and/or REDCap to crash.  Composer's class loader automatically handles cases like calling **require** for the same class from multiple modules.  While this does mean that modules could potentially end up using the version of a dependency from another module instead of their own, this is rarely an issue in practice (as evidenced by the WordPress community's reliance on composer for plugin & theme dependencies).  Implementing a more complex dependency management system similar to Drupal's has been discussed, but such an effort is not likely since the current method is generally not an issue in practice.

If you would like to create a library to share between multiple modules, [composer can also use github as a repo](https://getcomposer.org/doc/05-repositories.md#loading-a-package-from-a-vcs-repository).

### Unit Testing

Standard PHPUnit unit testing is supported within modules.  If anyone is interested in collaborating to add support for javascript unit testing as well, please let us know.  PHP test classes can be added under the `tests` directory in your module as follows.

```php
<?php namespace YourNamespace\YourExternalModule;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once __DIR__ . '/../../../redcap_connect.php';

class YourExternalModuleTest extends \ExternalModules\ModuleBaseTest
{
   function testYourMethod(){
      $expected = 'expected value';
      $actual1 = $this->module->yourMethod();

      // Shorter syntax without explicitly specifying "->module" is also supported.
      $actual2 = $this->yourMethod();

      $this->assertSame($expected, $actual1);
      $this->assertSame($expected, $actual2);
   }
}
```


### Example config.json file

For reference, below is a nearly comprehensive example of the types of things that can be included in a module's config.json file.

``` json
{
   "name": "Configuration Example",

   "namespace": "Vanderbilt\\ConfigurationExampleExternalModule",

   "description": "Example module to show off all the options available",
   
   "documentation": "README.pdf",

   "authors": [
      {
         "name": "Jon Snow",
         "email": "jon.snow@vumc.org",
         "institution": "Vanderbilt University Medical Center"
      },
      {
         "name": "Arya Stark",
         "email": "arya.stark@vumc.org",
         "institution": "Vanderbilt University Medical Center"
      }
   ],

   "framework-version": 2,

   "permissions": [
      "redcap_save_record",
      "redcap_data_entry_form"
   ],

   "enable-every-page-hooks-on-system-pages": false,

   "links": {
      "project": [
         {
            "name": "Configuration Page",
            "icon": "fas fa-receipt",
            "url": "configure.php"
         }
      ],
      "control-center": [
         {
            "name": "SystemConfiguration Page",
            "icon": "fas fa-receipt",
            "url": "configure_system.php"
         }
      ],
   },

   "no-auth-pages": [
      "public-page"
   ],

   "system-settings": [
      {
         "key": "system-file",
         "name": "System Upload",
         "required": false,
         "type": "file",
         "repeatable": false
      },
      {
         "key": "system-checkbox",
         "name": "System Checkbox",
         "required": false,
         "type": "checkbox",
         "repeatable": false
      },
      {
         "key": "system-project",
         "name": "Project",
         "required": false,
         "type": "project-id",
         "repeatable": false
      },
      {
         "key": "test-list",
         "name": "List of Sub Settings",
         "required": true,
         "type": "sub_settings",
         "repeatable":true,
         "sub_settings":[
            {
               "key": "system_project_sub",
               "name": "System Project",
               "required": true,
               "type": "project-id"
            },
            {
               "key": "system_project_text",
               "name": "Sub Text Field",
               "required": true,
               "type": "text"
            }
         ]
      }
   ],

   "project-settings": [
      {
         "key": "descriptive-text",
         "name": "This is just a descriptive field with only static text and no input field.",
         "type": "descriptive"
      },
      {
         "key": "instructions-field",
         "name": "Instructions text box",
         "type": "textarea"
      },
      {
         "key": "custom-field1",
         "name": "Custom Field 1",
         "type": "custom",
         "source": "js/test_javascript.js",
         "functionName": "ExternalModulesOptional.customTextAlert"
      },
      {
         "key": "custom-field2",
         "name": "Custom Field 2",
         "type": "custom",
         "source": "extra_types.js",
         "functionName": "ExternalModulesOptional.addColorToText"
      },
      {
         "key": "test-list2",
         "name": "List of Sub Settings",
         "required": true,
         "type": "sub_settings",
         "repeatable":true,
         "sub_settings":[
            {
            "key": "form-name",
            "name": "Form name",
            "required": true,
            "type": "form-list"
            },
            {
               "key": "arm-name",
               "name": "Arm name",
               "required": true,
               "type": "arm-list"
            },
            {
               "key": "event-name",
               "name": "Event name",
               "required": true,
               "type": "event-list"
            },
            {
            "key": "test-text",
            "name": "Text Field",
            "required": true,
            "type": "text"
            }
         ]
      },
      {
         "key": "text-area",
         "name": "Text Area",
         "required": true,
         "type": "textarea",
         "repeatable": true
      },
      {
         "key": "rich-text-area",
         "name": "Rich Text Area",
         "type": "rich-text"
      },
      {
         "key": "field",
         "name": "Field",
         "required": false,
         "type": "field-list",
         "repeatable": false
      },
      {
         "key": "dag",
         "name": "Data Access Group",
         "required": false,
         "type": "dag-list",
         "repeatable": false
      },
      {
         "key": "user",
         "name": "Users",
         "required": false,
         "type": "user-list",
         "repeatable": false
      },
      {
         "key": "user-role",
         "name": "User Role",
         "required": false,
         "type": "user-role-list",
         "repeatable": false
      },
      {
         "key": "file",
         "name": "File Upload",
         "required": false,
         "type": "file",
         "repeatable": false
      },
      {
         "key": "checkbox",
         "name": "Test Checkbox",
         "required": false,
         "type": "checkbox",
         "repeatable": false
      },
      {
         "key": "project",
         "name": "Other Project",
         "required": false,
         "type": "project-id",
         "repeatable": false
      }
   ],
   "crons": [
      {
         "cron_name": "cron1",
         "cron_description": "Cron that runs every 30 minutes to do Y",
         "method": "cron1",
         "cron_frequency": "1800",
         "cron_max_run_time": "60"
      },
      {
         "cron_name": "cron2",
         "cron_description": "Cron that runs daily to do YY",
         "method": "some_other_method",
         "cron_frequency": "86400",
         "cron_max_run_time": "1200"
      },
      {
         "cron_name": "cron3",
         "cron_description": "Cron that runs daily at 1:15 am to do YYY",
         "method": "some_other_method_3",
         "cron_hour": 1,
         "cron_minute": 15
      },
      {
         "cron_name": "cron4",
         "cron_description": "Cron that runs on Mondays at 2:25 pm to do YYYY",
         "method": "some_other_method_4",
         "cron_hour": 14,
         "cron_minute": 25,
         "cron_weekday": 1
      },
      {
         "cron_name": "cron5",
         "cron_description": "Cron that runs on the second of each month at 4:30 pm to do YYYYY",
         "method": "some_other_method_5",
         "cron_hour": 16,
         "cron_minute": 30,
      }
   ],
   "compatibility": {
      "php-version-min": "5.4.0",
      "php-version-max": "5.6.2",
      "redcap-version-min": "7.0.0",
      "redcap-version-max": ""
   }
}
```
