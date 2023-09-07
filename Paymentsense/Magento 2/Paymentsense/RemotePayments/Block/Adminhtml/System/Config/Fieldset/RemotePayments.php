<?php
/*
 * Copyright (C) 2022 Paymentsense Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author      Paymentsense
 * @copyright   2022 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Paymentsense\RemotePayments\Block\Adminhtml\System\Config\Fieldset;

/**
 * Renderer for the RemotePayments payment method at the admin panel
 */
class RemotePayments extends \Magento\Config\Block\System\Config\Form\Fieldset
{
    /**
     * Adds a custom css class
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return string
     */
    protected function _getFrontendClass($element)
    {
        return parent::_getFrontendClass($element) . ' with-button';
    }

    /**
     * Returns the header title
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return string
     */
    protected function _getHeaderTitleHtml($element)
    {
        $htmlId = $element->getHtmlId();
        $html = '<div class="config-heading">';
        $html .= '<div class="button-container"><button type="button"' .
            ' class="button action-configure' .
            '" id="' .
            $htmlId .
            '-head" onclick="togglePaymentsenseMethod.call(this, \'' .
            $htmlId .
            "', '" .
            $this->getUrl(
                'adminhtml/*/state'
            ) . '\'); return false;"><span class="state-closed">' . __(
                'Configure'
            ) . '</span><span class="state-opened">' . __(
                'Close'
            ) . '</span></button>';

        $html .= '</div>';
        $html .= '<div class="heading"><strong>' . $element->getLegend() . '</strong>';

        if ($element->getComment()) {
            $html .= '<span>' . $element->getComment() . '</span>';
        }
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Returns the header comment
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return string
     */
    protected function _getHeaderCommentHtml($element)
    {
        return '';
    }

    /**
     * Gets collapsed state on-load
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return bool
     */
    protected function _isCollapseState($element)
    {
        return false;
    }

    /**
     * Adds a JavaScript code
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return string
     */
    // @codingStandardsIgnoreLine
    protected function _getExtraJs($element)
    {
        $script = "require(['jquery', 'prototype'], function($){
            window.togglePaymentsenseMethod = function (id, url) {
                Fieldset.toggleCollapse(id, url);
            }
        });";
        return $this->_jsHelper->getScript($script);
    }
}
