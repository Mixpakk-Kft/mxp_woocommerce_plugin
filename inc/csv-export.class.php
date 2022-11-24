<?php

class Mixpakk_CSV_Builder
{

    public function __construct($csv_content_details)
    {
        $this->csv_content_details = $csv_content_details;

        $this->set_csv_header();
        $this->set_csv_body();
    }

    public function set_csv_header()
    {
        $this->csv_header = array(
            'saturday',
            'referenceid',
            'cod',
            'sender_id',
            'sender',
            'sender_country_code',
            'sender_zip',
            'sender_city',
            'sender_address',
            'sender_apartment',
            'sender_phone',
            'sender_email',
            'consignee_id',
            'consignee',
            'consignee_zip',
            'consignee_city',
            'consignee_address',
            'consignee_apartment',
            'consignee_phone',
            'consignee_email',
            'weight',
            'comment',
            'group_id',
            'pick_up_point',
            'x',
            'y',
            'z',
            'customcode',
            'item_no',
        );
    }

    public function set_csv_body()
    {
        $this->csv_body = $this->csv_content_details;
    }

    public function get_csv_header()
    {
        return $this->$csv_header;
    }

    public function get_csv_body()
    {
        return $this->$csv_body;
    }

    public function build_csv()
    {
        return array(
            'csv_header' => $this->csv_header,
            'csv_body' => $this->csv_body,
        );
    }
}

class Mixpakk_CSV_Export
{

    public function export($csv_content)
    {
        $delimiter = ';';
        $filename = 'mixpakk-orders-' . date('Y-m-d') . '.csv';
        $f = fopen('php://memory', 'w');

        fputcsv($f, $csv_content['csv_header'], $delimiter);

        foreach ($csv_content['csv_body'] as $line_details) {
            fputcsv($f, $line_details, $delimiter);
        }

        fseek($f, 0);
        header('Content-Type: text/csv;charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);

        exit;

        // print_r( $csv_content );
    }
}
