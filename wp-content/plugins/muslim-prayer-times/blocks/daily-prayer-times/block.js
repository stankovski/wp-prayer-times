(function(blocks, element, blockEditor, components) {
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var ToggleControl = components.ToggleControl;
    var SelectControl = components.SelectControl;
    var ColorPicker = components.ColorPicker;
    var RangeControl = components.RangeControl;
    
    // Get plugin URL for icons
    var pluginUrl = (typeof wpPrayerTimesData !== 'undefined') ? wpPrayerTimesData.pluginUrl : '';
    
    // Define icon paths
    var prayerIcons = {
        fajr: pluginUrl + '/assets/icons/fajr.svg',
        sunrise: pluginUrl + '/assets/icons/sunrise.svg',
        dhuhr: pluginUrl + '/assets/icons/dhuhr.svg',
        asr: pluginUrl + '/assets/icons/asr.svg',
        maghrib: pluginUrl + '/assets/icons/maghrib.svg',
        isha: pluginUrl + '/assets/icons/isha.svg'
    };
    
    // Register the block
    registerBlockType('prayer-times/daily-prayer-times', {
        title: 'Daily Prayer Times',
        icon: 'editor-table',
        category: 'muslim-prayer-times',
        supports: {
            align: true
        },
        
        // Block attributes - all styling options available to users
        attributes: {
            className: {
                type: 'string',
                default: '',
            },
            align: {
                type: 'string',
                default: 'center',
            },
            textColor: {
                type: 'string',
                default: '',
            },
            backgroundColor: {
                type: 'string',
                default: '',
            },
            headerColor: {
                type: 'string',
                default: '',
            },
            showDate: {
                type: 'boolean',
                default: true,
            },
            showHijriDate: {
                type: 'boolean',
                default: true,
            },
            showSunrise: {
                type: 'boolean',
                default: true,
            },
            tableStyle: {
                type: 'string',
                default: 'default',
            },
            fontSize: {
                type: 'number',
                default: 16,
            },
            showArrows: {
                type: 'boolean',
                default: true,
            },
        },
        
        // Editor UI
        edit: function(props) {
            var attributes = props.attributes;
            
            // Function to update text alignment
            function onChangeAlign(newAlign) {
                props.setAttributes({ align: newAlign });
            }
            
            // Function to update text color
            function onChangeTextColor(newColor) {
                props.setAttributes({ textColor: newColor.hex });
            }
            
            // Function to update background color
            function onChangeBackgroundColor(newColor) {
                props.setAttributes({ backgroundColor: newColor.hex });
            }
            
            // Function to update header color
            function onChangeHeaderColor(newColor) {
                props.setAttributes({ headerColor: newColor.hex });
            }
            
            // Function to toggle date display
            function onToggleDate(newVal) {
                props.setAttributes({ showDate: newVal });
                
                // Hide Hijri date if main date is hidden
                if (!newVal) {
                    props.setAttributes({ showHijriDate: false });
                }
            }
            
            // Function to toggle Hijri date display
            function onToggleHijriDate(newVal) {
                props.setAttributes({ showHijriDate: newVal });
            }
            
            // Function to toggle sunrise display
            function onToggleSunrise(newVal) {
                props.setAttributes({ showSunrise: newVal });
            }
            
            // Function to change table style
            function onChangeTableStyle(newStyle) {
                props.setAttributes({ tableStyle: newStyle });
            }
            
            // Function to change font size
            function onChangeFontSize(newSize) {
                props.setAttributes({ fontSize: newSize });
            }
            
            // Function to toggle navigation arrows display
            function onToggleArrows(newVal) {
                props.setAttributes({ showArrows: newVal });
            }
            
            // Helper function to create a prayer cell with icon
            function createPrayerNameCell(prayerName) {
                var iconName = prayerName.toLowerCase();
                var iconPath = prayerIcons[iconName] || '';
                var iconElement = iconPath ? 
                    el('img', { 
                        src: iconPath,
                        className: 'prayer-icon',
                        alt: prayerName
                    }) : null;
                
                return el('td', { className: 'prayer-name' }, 
                    iconElement, 
                    ' ' + prayerName
                );
            }
            
            // Helper function to create a prayer times table
            function createPrayerTimesTable(day) {
                // Set up inline styles based on attributes
                var tableStyle = {};
                if (attributes.backgroundColor) {
                    tableStyle.backgroundColor = attributes.backgroundColor;
                }
                if (attributes.textColor) {
                    tableStyle.color = attributes.textColor;
                }
                
                var headerStyle = {};
                if (attributes.headerColor) {
                    headerStyle.backgroundColor = attributes.headerColor;
                }
                
                return el('table', { 
                    className: 'prayer-times-table ' + ('table-style-' + attributes.tableStyle),
                    style: tableStyle 
                },
                    el('thead', {},
                        el('tr', { style: headerStyle },
                            el('th', {}, 'Prayer'),
                            el('th', {}, 'Athan'),
                            el('th', {}, 'Iqama')
                        )
                    ),
                    el('tbody', {},
                        el('tr', {},
                            createPrayerNameCell('Fajr'),
                            el('td', {}, '5:30 AM'),
                            el('td', { className: 'iqama-time' }, '5:50 AM')
                        ),
                        attributes.showSunrise && el('tr', { className: 'sunrise-row' },
                            createPrayerNameCell('Sunrise'),
                            el('td', { colSpan: '2' }, '7:00 AM')
                        ),
                        el('tr', {},
                            createPrayerNameCell('Dhuhr'),
                            el('td', {}, '12:30 PM'),
                            el('td', { className: 'iqama-time' }, '12:45 PM')
                        ),
                        el('tr', {},
                            createPrayerNameCell('Asr'),
                            el('td', {}, '3:45 PM'),
                            el('td', { className: 'iqama-time' }, '4:00 PM')
                        ),
                        el('tr', {},
                            createPrayerNameCell('Maghrib'),
                            el('td', {}, '6:00 PM'),
                            el('td', { className: 'iqama-time' }, '6:05 PM')
                        ),
                        el('tr', {},
                            createPrayerNameCell('Isha'),
                            el('td', {}, '7:30 PM'),
                            el('td', { className: 'iqama-time' }, '7:45 PM')
                        )
                    )
                );
            }
            
            // Create sample dates for preview
            var previewDates = [
                { date: 'Today', hijri: '12 Ramadan 1445H' },
                { date: 'Tomorrow', hijri: '13 Ramadan 1445H' },
                { date: 'Day after tomorrow', hijri: '14 Ramadan 1445H' }
            ];
            
            // Set up container style
            var containerStyle = { textAlign: attributes.align };
            if (attributes.fontSize) {
                containerStyle.fontSize = attributes.fontSize + 'px';
            }
            
            // Block preview in editor
            return [
                // Inspector controls (sidebar)
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, { title: 'Display Settings', initialOpen: true },
                        el(ToggleControl, {
                            label: 'Show Date',
                            checked: attributes.showDate,
                            onChange: onToggleDate
                        }),
                        attributes.showDate && el(ToggleControl, {
                            label: 'Show Hijri Date',
                            checked: attributes.showHijriDate,
                            onChange: onToggleHijriDate
                        }),
                        el(ToggleControl, {
                            label: 'Show Sunrise',
                            checked: attributes.showSunrise,
                            onChange: onToggleSunrise
                        }),
                        el(SelectControl, {
                            label: 'Text Alignment',
                            value: attributes.align,
                            options: [
                                { label: 'Left', value: 'left' },
                                { label: 'Center', value: 'center' },
                                { label: 'Right', value: 'right' }
                            ],
                            onChange: onChangeAlign
                        }),
                        el(RangeControl, {
                            label: 'Font Size',
                            value: attributes.fontSize,
                            min: 12,
                            max: 30,
                            onChange: onChangeFontSize
                        }),
                        el(SelectControl, {
                            label: 'Table Style',
                            value: attributes.tableStyle,
                            options: [
                                { label: 'Default', value: 'default' },
                                { label: 'Bordered', value: 'bordered' },
                                { label: 'Striped', value: 'striped' },
                                { label: 'Minimal', value: 'minimal' }
                            ],
                            onChange: onChangeTableStyle
                        })
                    ),
                    el(PanelBody, { title: 'Color Settings', initialOpen: false },
                        el('div', {},
                            el('label', {}, 'Text Color'),
                            el(ColorPicker, {
                                color: attributes.textColor,
                                onChangeComplete: onChangeTextColor
                            })
                        ),
                        el('div', { style: { marginTop: '20px' } },
                            el('label', {}, 'Background Color'),
                            el(ColorPicker, {
                                color: attributes.backgroundColor,
                                onChangeComplete: onChangeBackgroundColor
                            })
                        ),
                        el('div', { style: { marginTop: '20px' } },
                            el('label', {}, 'Header Color'),
                            el(ColorPicker, {
                                color: attributes.headerColor,
                                onChangeComplete: onChangeHeaderColor
                            })
                        )
                    )
                ),
                
                // Block preview
                el('div', { className: props.className + ' wp-block-prayer-times-daily-prayer-times', style: containerStyle },
                    el('div', { className: 'prayer-times-carousel' },
                        el('div', { className: 'prayer-times-carousel-inner' },
                            // Show just one slide in the editor preview for simplicity
                            el('div', { className: 'prayer-times-carousel-item' },
                                attributes.showDate && el('div', { className: 'prayer-times-date' }, 
                                    el('div', { className: 'gregorian-date' }, 'Today\'s Prayer Times'),
                                    attributes.showHijriDate && el('div', { className: 'hijri-date' }, '12 Ramadan 1445H')
                                ),
                                createPrayerTimesTable()
                            )
                        ),
                        el('div', { className: 'prayer-times-carousel-dots' },
                            el('div', { className: 'prayer-times-carousel-dot active' }),
                            el('div', { className: 'prayer-times-carousel-dot' }),
                            el('div', { className: 'prayer-times-carousel-dot' }),
                            el('div', { className: 'prayer-times-carousel-dot' }),
                            el('div', { className: 'prayer-times-carousel-dot' })
                        )
                    ),
                    el('p', { className: 'prayer-times-note', style: { fontSize: '0.8em', opacity: 0.7, marginTop: '10px' } },
                        'Note: This is a preview. A carousel with 5 days of prayer times will be displayed on the frontend.'
                    )
                )
            ];
        },
        
        // Save function returns null because this block is rendered on the server
        save: function() {
            return null;
        }
    });
}(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components
));
