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
    registerBlockType('prayer-times/live-prayer-times', {
        title: 'Live Prayer Times',
        icon: 'calendar-alt',
        category: 'prayer-times',
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
            headerTextColor: {
                type: 'string',
                default: '',
            },
            highlightColor: {
                type: 'string',
                default: '',
            },
            clockColor: {
                type: 'string',
                default: '',
            },
            clockSize: {
                type: 'number',
                default: 40,
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
            showSeconds: {
                type: 'boolean',
                default: true,
            },
            showChanges: {
                type: 'boolean',
                default: true,
            },
            changeColor: {
                type: 'string',
                default: '#ff0000',
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
            
            // Function to update header text color
            function onChangeHeaderTextColor(newColor) {
                props.setAttributes({ headerTextColor: newColor.hex });
            }
            
            // Function to update highlight color
            function onChangeHighlightColor(newColor) {
                props.setAttributes({ highlightColor: newColor.hex });
            }
            
            // Function to update clock color
            function onChangeClockColor(newColor) {
                props.setAttributes({ clockColor: newColor.hex });
            }
            
            // Function to change clock size
            function onChangeClockSize(newSize) {
                props.setAttributes({ clockSize: newSize });
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
            
            // Function to toggle seconds display
            function onToggleSeconds(newVal) {
                props.setAttributes({ showSeconds: newVal });
            }
            
            // Function to toggle showing changes
            function onToggleShowChanges(newVal) {
                props.setAttributes({ showChanges: newVal });
            }
            
            // Function to update change color
            function onChangeChangeColor(newColor) {
                props.setAttributes({ changeColor: newColor.hex });
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
            function createPrayerTimesTable() {
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
                if (attributes.headerTextColor) {
                    headerStyle.color = attributes.headerTextColor;
                }
                
                // Style for the athan times (highlighted)
                var highlightStyle = {};
                if (attributes.highlightColor) {
                    highlightStyle.color = attributes.highlightColor;
                }
                
                // Style for changes column
                var changeStyle = {
                    color: attributes.changeColor || '#ff0000'
                };
                
                // Table headers including the changes column if enabled
                var tableHeaders = [
                    el('th', { style: headerStyle }, ''),
                    el('th', { style: headerStyle }, 'Athan'),
                    el('th', { style: headerStyle }, 'Iqama')
                ];
                
                if (attributes.showChanges) {
                    tableHeaders.push(el('th', { 
                        style: Object.assign({}, headerStyle, changeStyle),
                        className: 'changes-column'
                    }, 'Jun 21'));
                }
                
                return el('table', { 
                    className: 'prayer-times-live-table ' + ('table-style-' + attributes.tableStyle),
                    style: tableStyle 
                },
                    el('thead', {},
                        el('tr', { style: headerStyle }, tableHeaders)
                    ),
                    el('tbody', {},
                        el('tr', { className: 'next-prayer' },
                            createPrayerNameCell('Fajr'),
                            el('td', { style: highlightStyle }, '5:30 AM'),
                            el('td', { className: 'iqama-time' }, '5:50 AM'),
                            attributes.showChanges && el('td', { 
                                className: 'changes-column',
                                style: changeStyle 
                            }, 
                                el('span', { className: 'time-change' }, '6:00 AM')
                            )
                        ),
                        attributes.showSunrise && el('tr', { className: 'sunrise-row' },
                            createPrayerNameCell('Sunrise'),
                            el('td', { colSpan: attributes.showChanges ? '1' : '2' }, '7:00 AM'),
                            attributes.showChanges && el('td', {}),
                            attributes.showChanges && el('td', { 
                                className: 'changes-column',
                                style: changeStyle 
                            })
                        ),
                        el('tr', {},
                            createPrayerNameCell('Dhuhr'),
                            el('td', { style: highlightStyle }, '12:30 PM'),
                            el('td', { className: 'iqama-time' }, '12:45 PM'),
                            attributes.showChanges && el('td', { 
                                className: 'changes-column' 
                            })
                        ),
                        el('tr', {},
                            createPrayerNameCell('Asr'),
                            el('td', { style: highlightStyle }, '3:45 PM'),
                            el('td', { className: 'iqama-time' }, '4:00 PM'),
                            attributes.showChanges && el('td', { 
                                className: 'changes-column',
                                style: changeStyle 
                            }, 
                                el('span', { className: 'time-change' }, '4:15 PM')
                            )
                        ),
                        el('tr', {},
                            createPrayerNameCell('Maghrib'),
                            el('td', { style: highlightStyle }, '6:00 PM'),
                            el('td', { className: 'iqama-time' }, '6:05 PM'),
                            attributes.showChanges && el('td', { 
                                className: 'changes-column'
                            })
                        ),
                        el('tr', {},
                            createPrayerNameCell('Isha'),
                            el('td', { style: highlightStyle }, '7:30 PM'),
                            el('td', { className: 'iqama-time' }, '7:45 PM'),
                            attributes.showChanges && el('td', { 
                                className: 'changes-column'
                            })
                        )
                    )
                );
            }
            
            // Get current time for preview
            var now = new Date();
            var hours = now.getHours();
            var minutes = now.getMinutes();
            var seconds = now.getSeconds();
            var ampm = hours >= 12 ? 'PM' : 'AM';
                       
            // Format hours and minutes
            var hoursStr = hours.toString().padStart(2, '0');
            var minutesStr = minutes.toString().padStart(2, '0');
            var secondsStr = seconds.toString().padStart(2, '0');
            
            // Create clock elements for preview
            var clockElements = [
                hoursStr + ':' + minutesStr
            ];
            
            if (attributes.showSeconds) {
                clockElements.push(el('span', { className: 'clock-small' }, ':' + secondsStr));
            }
            
            // Set up container style
            var containerStyle = { textAlign: attributes.align };
            if (attributes.fontSize) {
                containerStyle.fontSize = attributes.fontSize + 'px';
            }
            
            // Set up clock style
            var clockStyle = {};
            if (attributes.clockColor) {
                clockStyle.color = attributes.clockColor;
            }
            if (attributes.clockSize) {
                clockStyle.fontSize = attributes.clockSize + 'px';
            }
            
            // Define hijri date styles
            var hijriArabicStyle = {};
            if (attributes.highlightColor) {
                hijriArabicStyle.color = attributes.highlightColor;
            }
            
            // Block preview in editor
            return [
                // Inspector controls (sidebar)
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, { title: 'Clock Settings', initialOpen: true },
                        el(RangeControl, {
                            label: 'Clock Size',
                            value: attributes.clockSize,
                            min: 20,
                            max: 260,
                            onChange: onChangeClockSize
                        }),
                        el('div', {},
                            el('label', {}, 'Clock Color'),
                            el(ColorPicker, {
                                color: attributes.clockColor,
                                onChangeComplete: onChangeClockColor
                            })
                        ),
                        el(ToggleControl, {
                            label: 'Show Seconds',
                            checked: attributes.showSeconds,
                            onChange: onToggleSeconds
                        })
                    ),
                    el(PanelBody, { title: 'Display Settings', initialOpen: false },
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
                        el(ToggleControl, {
                            label: 'Show Upcoming Changes',
                            checked: attributes.showChanges,
                            onChange: onToggleShowChanges
                        }),
                        attributes.showChanges && el('div', { style: { marginTop: '10px' } },
                            el('label', {}, 'Change Indicator Color'),
                            el(ColorPicker, {
                                color: attributes.changeColor,
                                onChangeComplete: onChangeChangeColor
                            })
                        ),
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
                            el('label', {}, 'Header Background Color'),
                            el(ColorPicker, {
                                color: attributes.headerColor,
                                onChangeComplete: onChangeHeaderColor
                            })
                        ),
                        el('div', { style: { marginTop: '20px' } },
                            el('label', {}, 'Header Text Color'),
                            el(ColorPicker, {
                                color: attributes.headerTextColor,
                                onChangeComplete: onChangeHeaderTextColor
                            })
                        ),
                        el('div', { style: { marginTop: '20px' } },
                            el('label', {}, 'Highlight Color (for Athan times & Hijri Arabic)'),
                            el(ColorPicker, {
                                color: attributes.highlightColor,
                                onChangeComplete: onChangeHighlightColor
                            })
                        )
                    )
                ),
                
                // Block preview
                el('div', { 
                    className: props.className + ' wp-block-prayer-times-live-prayer-times', 
                    style: containerStyle 
                },
                    el('div', { className: 'live-prayer-clock', style: clockStyle },
                        el('span', { className: 'live-time' }, clockElements)
                    ),
                    attributes.showDate && el('div', { className: 'prayer-times-date' }, 
                        el('div', { className: 'gregorian-date' }, 'Today, ' + now.toLocaleDateString()),
                        attributes.showHijriDate && [
                            el('div', { className: 'hijri-date' }, '12 Ramadan 1445H'),
                            el('div', { className: 'hijri-date-arabic', style: hijriArabicStyle }, '١٢ رمضان ١٤٤٥ هـ')
                        ]
                    ),
                    createPrayerTimesTable(),
                    el('p', { className: 'prayer-times-note', style: { fontSize: '0.8em', opacity: 0.7, marginTop: '10px' } },
                        'Note: This is a preview. Actual live time and prayer times will be displayed on the frontend.'
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
