# CFI

![Version](https://img.shields.io/badge/VERSION-1.0.5-0366d6.svg?style=for-the-badge)
![Joomla](https://img.shields.io/badge/joomla-3.7+-1A3867.svg?style=for-the-badge)
![Php](https://img.shields.io/badge/php-5.6+-8892BF.svg?style=for-the-badge)

_description in Russian [here](README.ru.md)_

System plugin for Joomla 3.7+ for importing and exporting articles with additional fields

The plugin is designed to import and export standard articles and custom additional fields for them.

Export is initially adapted for opening the resulting file in MS Excel or its analogs.

---

### File format description

Data is exported to a file and imported from a CSV file with the mandatory separator `;`.

The default file encoding, if the option «Convert encoding…» is not set: UTF-8 w/o BOM. It supports automatic conversion of the encoding specified in the plugin's only configuration.

The first line of the file is always the file field headers.

Reserved field names:

- `articleid` - id of the updated article, contains a value of **0** for the newly added article, ignores rows for articles with a non-existent id (**required field**);
- `articletitle` - the title of the article (**required field**);
- `articlecat` - id of the category of the article, for new articles if the field is absent or the id of the nonexistent category is specified, the category «Uncategorised» will be applied, for existing articles will be ignored;
- `articlelang` - article language, for new articles in the absence of a field the article will be available for all languages, for existing articles will be ignored;
- `articleintrotext` - introductory text of the article;
- `articlefulltext` - the full text of the article.

Fields **articleid** and **articletitle** are required, if they are missing, data from the file is not imported.

Any other main article fields are ignored.

Any other field names are taken as the names of additional article fields. In cases where the article does not contain the indicated additional fields, the latter will be ignored.

The discrepancy between the number of values ​​in the row and the discrepancy with the number of field headers leads to the refusal to process this row. When importing, additional article fields that are not in the file are not affected.

Data on import errors is stored in the *cfi.php* log in the standard Joomla log folder.

If there are no data import errors, the imported file is deleted, otherwise the file is saved in the standard folder of temporary Joomla files.

---

### Data Format

When exporting, data is written to the file as is, in the format in which it is stored in the database of your site: plain text, text with HTML markup, json structures and other complex string structures.

For standard additional Joomla fields of a list type that return the structure of the stored data in the form of unassociated arrays, json is returned to the resulting file. For non-standard fields, the structure `array::` is written before the json value in the file: this is necessary so that with the possible subsequent import of this data, the plugin can parse the json value from the file and substitute the prepared array for the corresponding field. If you don’t understand anything from the above phrase, it’s okay, just don’t touch the value of `array ::` in your file or delete this column completely in order to avoid damage to the data of the corresponding article field.

---

### Data protection during import

The data of additional fields are written to the database by direct queries and are not subjected to any processing. Due to the fact that this data may contain HTML markup tags, json strings or other string constructs containing specialized characters, **data is not shielded!** Please ensure the safety of the imported data at the stage of generating the file for importing data into Joomla .

**The plugin developer is not responsible for the incorrect content of the imported files, which may damage your site**.

---

More complete documentation here: <https://joomline.org/docs/80-documentation-extension-cfi.html>
