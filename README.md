# Paper Trail External Module v1.3.5
---------------------------------------------------------------------------------------

### Brief summary
The goal of this external module (ext-mod) is to streamline the creation of pdf files in REDCap when it needs to be automatically attached to an upload field in the same project. 
In it simplest form, it can create a pdf-version of a pre-determined REDCap instrument, when specific fields have been completed. 
You have full control over which fields must be completed before the pdf file is created, as well as selecting the desired upload field. 
Additionally, the ext-mod wouldn't be complete without a feature to remove the label "remove file and send it" from the upload field, 
and server side processing of large pdf files (recommended for instruments with in-line images). 
Finally, you can use its multi use-case functionality to defined several conditions, each generating their respective pdf file.

----------------------------------------------------------------------------------------------
### New feature --  Select the longitudinal event containing the upload field to which the PDF file must be attached to.  
* For longitudinal studies, you must specify which event the upload field receiving the PDF file is located.

### New feature -- Merge several instruments into a single PDF file: 
* The module now allows for specifying one or more instruments to be merged into a single PDF file; this is particularly useful when
signatures are captured in different instruments, at different times, and a record is needed of when the final/completed document was finally captured.

### New feature -- Server-side processing of large PDF files:
* (Recommended) Set this to "Enabled" if your project utilizes INLINEPDF and/or attached images as part of the forms that need to be combined into a PDF document. The file will be generated within 1 or 2 minutes of the record being saved and the defined conditions (below) being met.
* Set to "Disabled" if you wish to generate the PDF file in realtime. NOTE: This may impact user experience as it may introduce a delay when the record is saved.

### New feature --  Store PDF copy in PDF Survey Archival Tab: 
* Store a copy of the generated document in the PDF Survey Archival section, found in the File Repository (*Requires that surveys be used/configured in this project*)


---------------------------------------------------------------------------------------

##Setting up Paper Trail for your project

###Select the type of paper trail needed for your specific use case:
* <b>Single use-case:</b> only one condition must be met for generating a pdf file, containing one (or merging several) instrument(s), based on one or several fields being completed, and uploaded to only one upload field, i.e. generate a pdf file when all signatures are captured
* <b>Multiple use-cases:</b> one or several conditions can be met for generating their respective pdf file and uploading it into their respective upload fields, i.e. generate a pdf file for completed consent, OR generate a pdf file for completed assent. Currently, 
the module does not support OR-statements, and all use-cases must be designed as AND-statements. For example, one use-case must be defined for generating the pdf of the completed consent, and an
additional use-case must be defined for generating the pdf of the completed assent.

Please note that both use-cases are configured in the exact same way. The difference between the two is simple: multiple use-case allows for several conditions to be specified while single use-case does not.

###Using the drop down fields in the ext-mod configuration window, specify:
0. Press the (+) button to add an additional use-case condition <i>(only for Multi use-case option)</i>
0. Use-case name <i>(only for Multi use-case option)</i>
1. Enable/disable Server-Side PDF processing
2. Name of the instrument(s) expected to be saved in pdf format
3. Upload (target) field receiving the pdf file from step #4
4. Specify the arm in which the (target) upload field, from step #3, is to be found (required for longitudinal projects that have defined events)
5. Specify the form status to which the target field's form should be set after receiving the pdf file
6. Prefix of pdf file name from step #5; it will be concatenated to system constants i.e. prefix_project_id_record_id_date_time.pdf
7. Enable/disable storing a copy of the generated document in the PDF Survey Archival section, found in the File Repository (*Requires that surveys be used/configured in this project*)
8. Type of upload:
    8. Automatic file upload after all specified fields have been answered
    9. Controlled by a single Yes/No field triggering the upload - (not available - deprecated)
    10. Disable configuration for resetting selection in case troubleshooting is needed
11. Hide the "Remove File" and "Send it" from Target Upload Field

## Future Enhancements:
1. Enhance multiple use-case with a logic box for allowing complex conditions to be entered using REDCap's pseudo-code.
2. Provide control over which links are hidden in the upload fields
3. Add functionality to allow more than one target field
4. Add functionality to toggle REDCap Logo off the pdf file straight from the Ext-Mod
5. Prevent overwriting the pdf file if the triggering condition is met a subsequent time
6. Simplify the config's drop down choices, by removing unnecessary fields from its list (i.e. show only upload fields for target upload field drop down menu)

## Envisioned, Designed, and Developed by:
* [Lynn Sympson](https://community.projectredcap.org/users/27/lynnsimpson.html)
* [Dimo Dimitar](https://community.projectredcap.org/users/845/dimitardimitrov.html) 
* [Ed Morales](https://community.projectredcap.org/users/1044/eduardomorales.html) at EMORALES@BWH.HARVARD.EDU *


---------------------------------------------------------------------------------------
----------------- See this documentation in the REDCap consortium website by clicking: [Paper Trail v1.3.3](https://community.projectredcap.org/articles/88684/paper-trail-v133-tba.html) -----------------

*use for comments, questions, or concerns

---------------------------------------------------------------------------------------