# BS: a competency rated in two courses counts twice towards "all".
s#COUNT\(DISTINCT \{\$alias\}\.competencyid\)#COUNT({\$alias}.competencyid)#s;
