#CLI-Update

Proof of concept on how to update/manage Joomla from the command line 

Parameter:

php cli/update.php 

--core

--extension[=ID_OF_THE_EXTENSION]

--info

--sitename

--install=[FULL_PATH_TO_A_FILE|URL]

--remove=ID_OF_THE_EXTENSION

# Description

--core: 

Update the Joomla! Core CMS

--extension[=ID_OF_THE_EXTENSION]:

Update all or a single Extension

--info

Gives Information about all installed Extensions as a json

--sitename

Return the sitename

--install=[FULL_PATH_TO_A_FILE|URL]

Install a exentension, provide the full path to the package file or an URL to the package

--remove=ID_OF_THE_EXTENSION

Remove a extension, you have to provide the extension id
