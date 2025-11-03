/**
 * Changelogify Gutenberg Block
 */

(function (blocks, element, components, blockEditor) {
    const el = element.createElement;
    const { InspectorControls } = blockEditor;
    const { PanelBody, RangeControl, ToggleControl } = components;

    blocks.registerBlockType('changelogify/changelog', {
        title: 'Changelog',
        icon: 'list-view',
        category: 'widgets',
        attributes: {
            limit: {
                type: 'number',
                default: 5
            },
            showVersion: {
                type: 'boolean',
                default: true
            },
            showDate: {
                type: 'boolean',
                default: true
            }
        },

        edit: function (props) {
            const { attributes, setAttributes } = props;

            return el(
                'div',
                { className: props.className },
                [
                    el(
                        InspectorControls,
                        {},
                        el(
                            PanelBody,
                            { title: 'Changelog Settings', initialOpen: true },
                            [
                                el(RangeControl, {
                                    label: 'Number of releases to show',
                                    value: attributes.limit,
                                    onChange: (value) => setAttributes({ limit: value }),
                                    min: 1,
                                    max: 20
                                }),
                                el(ToggleControl, {
                                    label: 'Show version',
                                    checked: attributes.showVersion,
                                    onChange: (value) => setAttributes({ showVersion: value })
                                }),
                                el(ToggleControl, {
                                    label: 'Show date',
                                    checked: attributes.showDate,
                                    onChange: (value) => setAttributes({ showDate: value })
                                })
                            ]
                        )
                    ),
                    el(
                        'div',
                        {
                            style: {
                                padding: '20px',
                                background: '#f0f0f0',
                                border: '1px solid #ddd',
                                borderRadius: '4px'
                            }
                        },
                        [
                            el('p', { style: { margin: 0, textAlign: 'center' } },
                                '📋 Changelog Block'
                            ),
                            el('p', {
                                style: {
                                    margin: '10px 0 0 0',
                                    fontSize: '0.9em',
                                    textAlign: 'center',
                                    color: '#666'
                                }
                            },
                                `Showing ${attributes.limit} release(s)`
                            )
                        ]
                    )
                ]
            );
        },

        save: function () {
            // Rendered by PHP
            return null;
        }
    });
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.components,
    window.wp.blockEditor
);
