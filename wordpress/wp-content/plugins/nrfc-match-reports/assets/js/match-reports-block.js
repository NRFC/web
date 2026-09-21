(function (blocks, element, components, i18n, blockEditor) {
    var el = element.createElement;
    var __ = i18n.__;
    var PanelBody = components.PanelBody;
    var InspectorControls = blockEditor.InspectorControls;
    var RangeControl = components.RangeControl;

    blocks.registerBlockType('nrfc-match-reports/latest-reports', {
        title: __('Latest Match Reports', 'nrfc-match-reports'),
        icon: 'media-document',
        category: 'widgets',
        attributes: {
            count: {
                type: 'number',
                default: 5
            }
        },

        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            return [
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, { title: __('Settings', 'nrfc-match-reports'), initialOpen: true },
                        el(RangeControl, {
                            label: __('Number of reports to show', 'nrfc-match-reports'),
                            value: attributes.count,
                            onChange: function (val) {
                                setAttributes({ count: val });
                            },
                            min: 1,
                            max: 20
                        })
                    )
                ),
                el('div', { className: props.className },
                    el('div', { className: 'nrfc-match-reports-placeholder' },
                        el('span', { className: 'dashicons dashicons-media-document' }),
                        el('p', {}, __('Latest Match Reports Block', 'nrfc-match-reports')),
                        el('p', { className: 'nrfc-match-reports-details' }, 
                            __('Showing latest ', 'nrfc-match-reports') + attributes.count + __(' reports', 'nrfc-match-reports')
                        )
                    )
                )
            ];
        },

        save: function () {
            // Server-side rendering
            return null;
        }
    });
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.components,
    window.wp.i18n,
    window.wp.blockEditor
);
