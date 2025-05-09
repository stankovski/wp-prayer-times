(function(blocks, element, blockEditor, components) {
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var ToggleControl = components.ToggleControl;
    var SelectControl = components.SelectControl;
    var RangeControl = components.RangeControl;
    var ColorPicker = components.ColorPicker;
    
    // Register the block
    registerBlockType('prayer-times/monthly-prayer-times', {
        title: 'Monthly Prayer Times',
        icon: 'calendar',
        category: 'prayer-times',
        supports: {
            align: true
        },
        
        // Block attributes
        attributes: {
            className: {
                type: 'string',
                default: '',
            },
            align: {
                type: 'string',
                default: 'center',
            },
            headerTextColor: {
                type: 'string',
                default: '',
            },
            headerColor: {
                type: 'string',
                default: '',
            },
            tableStyle: {
                type: 'string',
                default: 'default',
            },
            fontSize: {
                type: 'number',
                default: 16,
            },
            showSunrise: {
                type: 'boolean',
                default: true,
            },
            showIqama: {
                type: 'boolean',
                default: true,
            },
            highlightToday: {
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
            
            // Function to update header text color
            function onChangeHeaderTextColor(newColor) {
                props.setAttributes({ headerTextColor: newColor.hex });
            }
            
            // Function to update header color
            function onChangeHeaderColor(newColor) {
                props.setAttributes({ headerColor: newColor.hex });
            }
            
            // Function to toggle sunrise row
            function onToggleSunrise(newVal) {
                props.setAttributes({ showSunrise: newVal });
            }
            
            // Function to toggle Iqama times
            function onToggleIqama(newVal) {
                props.setAttributes({ showIqama: newVal });
            }
            
            // Function to toggle highlighting today
            function onToggleHighlightToday(newVal) {
                props.setAttributes({ highlightToday: newVal });
            }
            
            // Function to change table style
            function onChangeTableStyle(newStyle) {
                props.setAttributes({ tableStyle: newStyle });
            }
            
            // Function to change font size
            function onChangeFontSize(newSize) {
                props.setAttributes({ fontSize: newSize });
            }
            
            // Set up inline styles based on attributes
            var containerStyle = { textAlign: attributes.align };
            if (attributes.fontSize) {
                containerStyle.fontSize = attributes.fontSize + 'px';
            }
            
            var tableStyle = {};
            
            var headerStyle = {};
            if (attributes.headerColor) {
                headerStyle.backgroundColor = attributes.headerColor;
            }
            if (attributes.headerTextColor) {
                headerStyle.color = attributes.headerTextColor;
            }
            
            // Get current month name for preview
            var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                              'July', 'August', 'September', 'October', 'November', 'December'];
            var currentDate = new Date();
            var currentMonthYear = monthNames[currentDate.getMonth()] + ' ' + currentDate.getFullYear();
            
            // Generate sample dates for the preview table
            var sampleDates = [];
            var currentDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
            var today = new Date().getDate();
            
            for (var i = 0; i < 7; i++) {
                var isToday = currentDay.getDate() === today;
                var isFriday = currentDay.getDay() === 5; // 5 = Friday (0 is Sunday in JS)
                sampleDates.push({
                    dayNumber: currentDay.getDate(),
                    dayName: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][currentDay.getDay()],
                    isToday: isToday,
                    isFriday: isFriday
                });
                currentDay.setDate(currentDay.getDate() + 1);
            }
            
            // Block preview in editor
            return [
                // Inspector controls (sidebar)
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, { title: 'Display Settings', initialOpen: true },
                        el(ToggleControl, {
                            label: 'Show Sunrise',
                            checked: attributes.showSunrise,
                            onChange: onToggleSunrise
                        }),
                        el(ToggleControl, {
                            label: 'Show Iqama Times Inline',
                            checked: attributes.showIqama,
                            onChange: onToggleIqama
                        }),
                        el(ToggleControl, {
                            label: 'Highlight Today',
                            checked: attributes.highlightToday,
                            onChange: onToggleHighlightToday
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
                            ],
                            onChange: onChangeTableStyle
                        })
                    ),
                    el(PanelBody, { title: 'Color Settings', initialOpen: false },
                        el('div', {},
                            el('label', {}, 'Header Text Color'),
                            el(ColorPicker, {
                                color: attributes.headerTextColor,
                                onChangeComplete: onChangeHeaderTextColor
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
                el('div', { className: props.className, style: containerStyle },
                    el('div', { className: 'prayer-times-month-header' },
                        el('button', { className: 'prev-page' }, '« Previous Month'),
                        el('h3', { className: 'month-name' }, currentMonthYear),
                        el('button', { className: 'next-page' }, 'Next Month »')
                    ),
                    el('div', { className: 'prayer-times-table-container' },
                        el('table', { 
                            className: 'prayer-times-table ' + ('table-style-' + attributes.tableStyle),
                            style: tableStyle 
                        },
                            el('thead', {},
                                el('tr', { style: headerStyle },
                                    el('th', {}, 'Date'),
                                    el('th', {}, 'Fajr'),
                                    !attributes.showIqama && el('th', {}, 'Fajr Iqama'),
                                    attributes.showSunrise && el('th', {}, 'Sunrise'),
                                    el('th', {}, 'Dhuhr'),
                                    !attributes.showIqama && el('th', {}, 'Dhuhr Iqama'),
                                    el('th', {}, 'Asr'),
                                    !attributes.showIqama && el('th', {}, 'Asr Iqama'),
                                    el('th', {}, 'Maghrib'),
                                    !attributes.showIqama && el('th', {}, 'Maghrib Iqama'),
                                    el('th', {}, 'Isha'),
                                    !attributes.showIqama && el('th', {}, 'Isha Iqama')
                                )
                            ),
                            el('tbody', {},
                                // Generate sample rows for preview
                                sampleDates.map(function(date, index) {
                                    var rowClasses = [];
                                    if (attributes.highlightToday && date.isToday) {
                                        rowClasses.push('today');
                                    }
                                    if (date.isFriday) {
                                        rowClasses.push('friday');
                                    }
                                    
                                    return el('tr', { 
                                        key: index,
                                        className: rowClasses.join(' ')
                                    },
                                        // Date column
                                        el('td', { className: 'date-column' },
                                            el('span', { className: 'day-name' }, date.dayName),
                                            el('span', { className: 'day-number' }, date.dayNumber)
                                        ),
                                        // Fajr column
                                        el('td', { className: 'prayer-column' },
                                            attributes.showIqama 
                                                ? [
                                                    el('span', { className: 'iqama-time' }, '5:50 AM'),
                                                    el('span', { className: 'athan-time' }, 'Athan: 5:30 AM')
                                                  ]
                                                : el('span', { className: 'athan-time' }, '5:30 AM')
                                        ),
                                        // Fajr Iqama (separate)
                                        !attributes.showIqama && el('td', { className: 'prayer-column iqama-column' },
                                            el('span', { className: 'iqama-time' }, '5:50 AM')
                                        ),
                                        // Sunrise column (optional)
                                        attributes.showSunrise && el('td', { className: 'prayer-column sunrise-column' },
                                            el('span', { className: 'athan-time' }, '7:00 AM')
                                        ),
                                        // Dhuhr column
                                        el('td', { className: 'prayer-column' },
                                            attributes.showIqama 
                                                ? [
                                                    el('span', { className: 'iqama-time' }, '12:45 PM'),
                                                    el('span', { className: 'athan-time' }, 'Athan: 12:30 PM')
                                                  ]
                                                : el('span', { className: 'athan-time' }, '12:30 PM')
                                        ),
                                        // Dhuhr Iqama (separate)
                                        !attributes.showIqama && el('td', { className: 'prayer-column iqama-column' },
                                            el('span', { className: 'iqama-time' }, '12:45 PM')
                                        ),
                                        // Asr column
                                        el('td', { className: 'prayer-column' },
                                            attributes.showIqama 
                                                ? [
                                                    el('span', { className: 'iqama-time' }, '4:00 PM'),
                                                    el('span', { className: 'athan-time' }, 'Athan: 3:45 PM')
                                                  ]
                                                : el('span', { className: 'athan-time' }, '3:45 PM')
                                        ),
                                        // Asr Iqama (separate)
                                        !attributes.showIqama && el('td', { className: 'prayer-column iqama-column' },
                                            el('span', { className: 'iqama-time' }, '4:00 PM')
                                        ),
                                        // Maghrib column
                                        el('td', { className: 'prayer-column' },
                                            attributes.showIqama 
                                                ? [
                                                    el('span', { className: 'iqama-time' }, '6:05 PM'),
                                                    el('span', { className: 'athan-time' }, 'Athan: 6:00 PM')
                                                  ]
                                                : el('span', { className: 'athan-time' }, '6:00 PM')
                                        ),
                                        // Maghrib Iqama (separate)
                                        !attributes.showIqama && el('td', { className: 'prayer-column iqama-column' },
                                            el('span', { className: 'iqama-time' }, '6:05 PM')
                                        ),
                                        // Isha column
                                        el('td', { className: 'prayer-column' },
                                            attributes.showIqama 
                                                ? [
                                                    el('span', { className: 'iqama-time' }, '7:45 PM'),
                                                    el('span', { className: 'athan-time' }, 'Athan: 7:30 PM')
                                                  ]
                                                : el('span', { className: 'athan-time' }, '7:30 PM')
                                        ),
                                        // Isha Iqama (separate)
                                        !attributes.showIqama && el('td', { className: 'prayer-column iqama-column' },
                                            el('span', { className: 'iqama-time' }, '7:45 PM')
                                        )
                                    );
                                })
                            )
                        )
                    ),
                    el('p', { className: 'prayer-times-note', style: { fontSize: '0.8em', opacity: 0.7, marginTop: '10px' } },
                        'Note: This is a preview. Actual prayer times will be displayed on the frontend.'
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
