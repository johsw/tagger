Keyword-import
--------------

This folder contains files for importing texts and then transform them
into a Tagger keyword-database.

***Import using JSON***

1. Create the JSON-file _keyword-texts.json_ (see below)
2. Run `json_create_wordstats()` (defined in _lib\_keyword.php_)
3. Run `json_create_keywords()` (defined in _lib\_keyword.php_)

---
_keyword-texts.json_ must be valid [JSON], encoded in `UTF-8` and be on the form:

    { "Keyword1ID": ["text1ForKeyword1", "text2ForKeyword1", ..],
      "Keyword2ID": ["text1ForKeyword2", "text2ForKeyword2", ..], .. }
As with JSON, standard whitespace (tab, space, newline, carriage return) outside quotation marks is ignored and can be added or removed to your liking.

For example:

    { "4673": ["Exciting new development in quantum physics ...", "Physics? Fact or myth ..."],
      "137": ["Danish national football team wins European Championship ..."] }
or the equivalent (but without whitespace):

    {"4673":["Exciting new development in quantum physics ...","Physics? Fact or myth ..."],"137":["Danish national football team wins European Championship ..."]}

[JSON]: http://json.org

