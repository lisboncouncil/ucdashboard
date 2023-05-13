# UserCentriCities Dashboard

Version 1.0 - 13th May 2023 <marcello.verona@lisboncouncil.net>



### Abstract

This documentation relates to a collection of modules, made for Drupal 9 (but also compatible with Drupal 10, with minimal changes), which allows a dashboard of city or region metrics as realised in the UserCentresCities site (https://www.usercentricities.eu/ucdashboard).

The ucdashboard module is the "logic" of the model. The ucservice module is the structure, in terms of content-type, fields and other entities *under the wood*.

Elements contained in the module :

* Content-types:
  * City/Region metric (machine name: ucservice)
* Permission:
  * City metric editor
  * City metric administrator
* Views: 
  * Data export (/ucdashboard-export/raw-data-csv)
  * Data export (/ucdashboard-export/raw-data-json)
* Conditional fields
  * a collections of rules related to the ucservice type and his own fields.



## Installation

You need to install in your module/custom folder the modules:

* ucdashboard
* ucservice
* highcharts

UCDashboard is a module that provide the dashboard itself.

The UCService modules provides the roles, the content-type the views and all the elements necessary for the management of the dashboard. 

The Highcharts is a simple wrapper for the well known library.

In the /modules page you will see something link this: 

![image-20230513162253627](./blob/main/doc-img/image-20230513162253627.png)

First of all, you have to install the dependencies.

```shell
# Install the required modules

composer require 'drupal/csv_serialization:^3.0'
composer require 'drupal/field_group:^3.4'
composer require 'drupal/field_label_long_text:^1.0'
composer require 'drupal/views_data_export:^1.3'
composer require 'drupal/workbench_moderation:^1.7'
composer require 'drupal/conditional_fields:^4.0@alpha'
composer require 'drupal/view_unpublished'

# Than enable the dependencies

drush en conditional_fields csv_serialization field_group field_label_long_text views_data_export workbench_moderation view_unpublished

# ... and now you can install the modules
drush en ucservice ucdashboard highcharts

```



Now, if everything was OK, in the Content-type you can see the "City/Region Metrics" type. 



### The ucservice content type

The ucservice content type represent the city metric. In case of new content, using also the alias /add-citymetric, the questionnaire is shown to the city representative for the data.
The form has a lot of automations (based on the additional module “Contiditional fields”). Every time the people answer “Yes” to a question of type yes/no, an additional and mandatory field “Evidence” appears.



## Data

No data are currently available. To enter some data, you can enter test data or import data with Migrate or another module.



## Customization



### Configuration file

The configuration file is in `ucdashboard/config/install/ucdashboard.settings.yml`

The anatomy of configuration file is the following:

```yaml
  threshold_low:  0.33
  threshold_medium:  0.66
  threshold_high:  1.0
  threshold_classes:
    - red
    - orange
    - green
  show_percentage: true
  revisions: 
      # Revision 2023 - it's the last revision with "op" = null
      - label: 2023
        op: null
        description: Last revision of the dashboard
        schema: 
          - key: 1.1
            name: Skills
            category: Enablers
            ff:
              - sk_locauth_pos_serv_des
              - sk_locauth_training_cs
              - sk_locauth_train_dscs
              - sk_locauth_training_cit
          - key: 1.2 
          ...
```

Here you can find some variables:

**threshold_**(low | medium | high) is the average score that a indicator must have in order to be classified as low (red), medium (orange) and high (green). You can change the threshold, by changing these values.

the **show_percentage** variables show (or hide) the percentages near to the name of the city. 

![image-20230513182230950](./doc-images/image-20230513182230950.png?raw=true) 



The **revisions** is the most important variables. 

In each revision you have the schema of the indicators that concur to the score of the city. You can have one or more revision. In case of two or more revisions you will have multiple tabs in the page. 

In the provided file you have two revisions:

![image-20230513182422403](./doc-images/image-20230513182422403.png?raw=true)

The first is the default (2023) and the last revision of the nodes are shown.

In the other (2022) are shown the revision of the node created before the 1st January 2023. This is defined by the variable "`op = before`" and the date (`date: 2023-01-01`).

Each revision has a potentially different pattern. Descending into the branches of the configuration is the actual schema, i.e. the data model, with categories, indicators and related fields. 

Each indicator has a "**category**" or "pillar" (by default Enablers, User-Centricity Performance, Outcome), a key (e.g. 1.1), a name (e.g. Skills) and an array of related field, the "ff" field.

The ff array represents the list of "yes/no" fields that cuncur to the indicator. 

Resuming, in instance in the dashboard you can see somethig like "1.1 Skills" and a green cell with "4/4" as value.  

This means that for all fields related to the indicator 1.1, named "Skills" (field_sk_locauth_pos_serv_des, 
field_sk_locauth_training_cs, field_sk_locauth_train_dscs, field_sk_locauth_training_cit) the user entered YES as an answer in 4 out of 4. Please note that each field in the configuration file, in the array 'ff' is named without the Drupal prefix 'field_'.



### Templates

Some of the text are hard coded in the templates. You can edit the text in the templates directly.

The principal templates are:

- ucdash_main.html.twig
  the main page -   `ucdashboard`
- ucdashboard_detail.html.twig
  the detail page of the city, with the score and the chart -   `ucdashboard/$node_id`
- ucdash_thankyou.html.twig
  the "thank you" page, used as redirect when the people add a new content of type ucdashboard -  `ucdashboard/thankyou`



### CSS settings

The style setting of the pages is configured in the file`ucdashboard/css/ucdasboard.css` Here all the principal styles are defined. 

It is important to remember that the module, in the UserCentriCities website, is used in a Bootstrap-based theme context (v. 5.2). Therefore, numerous visual aspects are defined by predefined Bootstrap classes. For instance, the link to add a new city to the dashboard uses the bootstrap-typical .btn and .btn-success classes, or some sections use the utility classes .m-3 or similar. 
It follows that the visual appearance of the dashboard will be all the more complete if the theme of the host site is realised with Bootstrap. Otherwise, more customisation work will be required.


