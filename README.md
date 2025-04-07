# Table of contents plugin

## What is it?

This content plugin creates a Table of Contents in an article.

## Usage

To include a Table of Contents, just add this to your article:

```plaintext
{toc}
```

There are also some optional parameters:

```plaintext
{toc minlevel=2 maxlevel=4 chapternumbers=true prefix=§}
```

* __minlevel__
Set the minimum heading level to include in the Table of Contents.

* __maxlevel__
Set the maximum heading level to include.

* __chapternumbers__
Set this to `true` to include chapter numbers in the headings and Table of Contents.

* __prefix__
If `chapternumbers=true`, this lets you define an additional string that will be inserted before a chapter number.

## License

Published under GNU Public License 2 (see LICENSE.txt).
