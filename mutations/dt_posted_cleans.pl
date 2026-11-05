# DT: The rule form renders the descriptions the browser posted back uncleaned.
s{\$condition\[\'description\'\] \= condition_manager\:\:description_html\(\(string\) \(\$condition\[\'description\'\] \?\? \'\'\)\)\;}{\$condition['description'] = (string) (\$condition['description'] ?? '');}s;
