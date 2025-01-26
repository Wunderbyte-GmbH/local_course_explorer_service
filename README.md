# Course Explorer Service

## Name
local_course_explorer_service

## Description
This is a backend of the Course Explorer (block_course_explorer) aggregating and providing formatted data to the block's frontend.
For correct work a web-service token is required (Site administration -> Server -> Manage tokens).
Created token should be saved in settings of Course Explorer (Site administration -> Plugins -> Scrolling down to Blocks
-> Course Explorer)

Some APIs in local_course_explorer_service aggregate data for third party services. E.g. course_exporter.php
exports data to Wordpress in case of www.mein.mintcampus.org. 
