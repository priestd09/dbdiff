<?php

/**
 * Compare the schemas of between databases.
 *
 * For two database schemas to be considered the same, they must have the same
 * tables, where each table has the same fields, and each field has the same
 * parameters.
 *
 * Field parameters that are compared are those that are given by the MySQL
 * 'SHOW COLUMNS' command. These are: the field's name, it's type, whether the
 * field can store null values, whether the column is indexed, the default
 * values and whether the field was created with the 'auto_increment' keyword.
 */
class DbDiff
{

    /**
     * Export the schema of the database into an array.
     *
     * @param string $config Config details for the database connection.
     * @param string $name Name or description of the database.
     * @return mixed|string An array structure of the exported schema, or an error string.
     */
    public static function export($config, $name)
    {
        $db = mysqli_connect($config['host'], $config['user'], $config['password']);

        if (!$db) {
            return null;
        }

        if (!mysqli_select_db($db, $config['name'])) {
            return null;
        }

        $result = mysqli_query($db, "SHOW TABLES");
        while ($row = mysqli_fetch_row($result)) {
            $tables[$row[0]] = array();
        }

        foreach ($tables as $table_name => $fields) {

            $result = mysqli_query($db, "SHOW COLUMNS FROM `" . $table_name . "`");
            while ($row = mysqli_fetch_assoc($result)) {
                $tables[$table_name][$row['Field']] = $row;
            }
        }

        mysqli_close($db);

        $data = array(
            'name' => $name,
            'time' => time(),
            'tables' => $tables
        );

        return $data;
    }

    /**
     * Compare two schemas (as generated by the 'export' method.)
     *
     * @param string $schema1 The first database schema.
     * @param string $schema2 The second database schema.
     * @return array The results of the comparison.
     */
    public static function compare($schema1, $schema2)
    {
        $tables1 = array_keys($schema1['tables']);
        $tables2 = array_keys($schema2['tables']);

        $tables = array_unique(array_merge($tables1, $tables2));
        $results = array();

        foreach ($tables as $table_name) {

            // Check tables exist in both databases

            if (!isset($schema1['tables'][$table_name])) {

                $results[$table_name][] = '<em>' . $schema1['name']
                        . '</em> is missing table: <code>' . $table_name
                        . '</code>.';

                continue;
            }

            if (!isset($schema2['tables'][$table_name])) {

                $results[$table_name][] = '<em>' . $schema2['name']
                        . '</em> is missing table: <code>' . $table_name
                        . '</code>.';

                continue;
            }

            // Check fields exist in both tables

            $fields = array_merge($schema1['tables'][$table_name], $schema2['tables'][$table_name]);

            foreach ($fields as $field_name => $field) {

                if (!isset($schema1['tables'][$table_name][$field_name])) {

                    $results[$table_name][] = '<em>' . $schema1['name']
                            . '</em> is missing field: <code>' . $field_name
                            . '</code>';

                    continue;
                }

                if (!isset($schema2['tables'][$table_name][$field_name])) {

                    $results[$table_name][] = '<em>' . $schema2['name']
                            . '</em> is missing field: <code>' . $field_name
                            . '</code>';

                    continue;
                }

                // Check that the specific parameters of the fields match

                $s1_params = $schema1['tables'][$table_name][$field_name];
                $s2_params = $schema2['tables'][$table_name][$field_name];

                foreach ($s1_params as $name => $details) {
                    if ($s1_params[$name] != $s2_params[$name]) {
                        $results[$table_name][] = 'Field <code>' . $field_name
                                . '</code> differs between databases for parameter \''
                                . $name . '\'. <em>' . $schema1['name']
                                . '</em> has \'' . $s1_params[$name]
                                . '\' and <em>' . $schema2['name']
                                . '</em> has \'' . $s2_params[$name] . '\'.';
                    }
                }
            }
        }

        return $results;
    }

}
