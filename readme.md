#Catalogue

A block for Moodle that shows a list of available courses, under a category, with tags, searchable, filterable, paging, tiles or summary... you know what: It's like if the 'My Courses' block actually listed all the courses, not just the ones you were enrolled in. Because Moodle needs a place that lists all the courses you might be able to enrol in, and the built-in page sucks.

One that also works when you are not yet logged on.

## installation

It's a block. Install it using the zip thingy if you have it enabled, or throw this in at /blocks/catalogue.

## settings

it supports tags, if you have then enabled in the advanced settings.

if you set a 'top category id' (which is the number id of the row of the category in the database) it will list courses under categories who have this id as a parent. You can set it to 0. You then get a list of those categories as buttons to filter the list to.

it's got all the things that 'my courses' block has, except the progress/my course stuff.
