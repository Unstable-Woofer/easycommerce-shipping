<tr>
    <th scope="row" class="titledesc"><?php echo $table_title; ?></th>
    <td id="<?php echo esc_attr(($this->id)); ?>_settings">
        <table class="shippingrows widefat">
            <col style="width:0%">
            <col style="width:0%">
            <col style="width:0%">
            <col style="width:100%;">
            <tbody style="border: 1px solid black;">
                <tr>
                    <td style="width: 100%">
                        <table class="shippingrows widefat">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th style="width: 20%;">Condition</th>
                                    <th style="width: 20%;">Min Value</th>
                                    <th style="width: 20%;">Max Value</th>
                                    <th style="width: 20%;">Shipping Class</th>
                                    <th style="width: 20%;">Shipping Fee (<?php echo get_woocommerce_currency_symbol(); ?>)</th>
                                </tr>
                            </thead>
                            <tbody id="easycommerce-shipping-rates">

                            </tbody>
                            <tfoot>
                                <tr>
                                    <th id="easycommerce-shipping-table-rate-buttons" colspan="10">
                                        <button class="button add" disabled>Add New Rate</button>
                                        <button class="button delete" disabled>Delete Selected Rates</button>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>
    </td>
</tr>