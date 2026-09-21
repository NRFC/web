(function (blocks, element, components, i18n, data, blockEditor) {
    var el = element.createElement;
    var __ = i18n.__;
    var SelectControl = components.SelectControl;
    var PanelBody = components.PanelBody;
    var InspectorControls = blockEditor.InspectorControls;
    var TextControl = components.TextControl;

    blocks.registerBlockType('nrfc-fixtures/fixtures-list', {
        title: __('Fixtures List', 'nrfc-fixtures'),
        icon: 'calendar-alt',
        category: 'widgets',
        attributes: {
            teamId: {
                type: 'string',
                default: ''
            },
            startDate: {
                type: 'string',
                default: ''
            },
            endDate: {
                type: 'string',
                default: ''
            }
        },

        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var teams = [];

            // We use the data passed from PHP via wp_localize_script
            if (window.NRFCFixturesBlockData && window.NRFCFixturesBlockData.teams) {
                teams = window.NRFCFixturesBlockData.teams.map(function(team) {
                    return { label: team.name, value: team.term_id.toString() };
                });
            }
            teams.unshift({ label: __('All Teams', 'nrfc-fixtures'), value: '' });

            return [
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, { title: __('Fixture Settings', 'nrfc-fixtures'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Filter by Team', 'nrfc-fixtures'),
                            value: attributes.teamId,
                            options: teams,
                            onChange: function (val) {
                                setAttributes({ teamId: val });
                            }
                        }),
                        el(TextControl, {
                            label: __('Start Date (YYYY-MM-DD)', 'nrfc-fixtures'),
                            value: attributes.startDate,
                            onChange: function (val) {
                                setAttributes({ startDate: val });
                            },
                            type: 'date'
                        }),
                        el(TextControl, {
                            label: __('End Date (YYYY-MM-DD)', 'nrfc-fixtures'),
                            value: attributes.endDate,
                            onChange: function (val) {
                                setAttributes({ endDate: val });
                            },
                            type: 'date'
                        })
                    )
                ),
                el('div', { className: props.className },
                    el('div', { className: 'nrfc-fixtures-placeholder' },
                        el('span', { className: 'dashicons dashicons-calendar-alt' }),
                        el('p', {}, __('Fixtures List Block', 'nrfc-fixtures')),
                        el('p', { className: 'nrfc-fixtures-details' }, 
                            attributes.teamId ? __('Team ID: ', 'nrfc-fixtures') + attributes.teamId : __('All Teams', 'nrfc-fixtures'),
                            attributes.startDate || attributes.endDate ? ' | ' : '',
                            attributes.startDate ? __('From: ', 'nrfc-fixtures') + attributes.startDate : '',
                            attributes.endDate ? ' ' + __('To: ', 'nrfc-fixtures') + attributes.endDate : ''
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
    window.wp.data,
    window.wp.blockEditor
);
