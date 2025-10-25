# Template Registry Idea

I am outlining my idea on the subject matter of this template registry.

## Editor User Interface
- there must be an editor pane for handlebars template (CRUD - new, search, open, edit, save, delete)
- there must be an editor pane for the json data (CRUD - new, search, open, edit, save, delete)
- this json data editor pane should respond to compile and preview actions
- these editor panes are stand-alone with respect to each other
- the load sample should populate both the handlebars template editor and the json data editor

## HandleBars Template

- filename is the template_ref but it has to be "namespaced" when referenced, maybe a URI
- it is part of a family, but it can be an orphan. So maybe there should be an identifier in the json - not sure how, a comment perhaps.

### Template Family
- it functions like a tag so that json data can point to a family or a specific template_ref handlebars template.
- I am thinking of the naming pattern <family name>:<template name>, the delimiter is not necessarily a colon because I want it to be referenced as a URI like in Github

## JSON Data

- I think this data json has to be portable at the onset. This is what I really want.
- therefore this data json must be validated by a schema that requires document.template_ref and data nodes

## Real World
- So the user of the editor should just know the handlebars template or family so the json data he is editing can just point to it.
- He can the validate his json data against the handlebars template (the fields must match).
- He can choose different handlebars templates.
- In the future he can choose different rendering template.
