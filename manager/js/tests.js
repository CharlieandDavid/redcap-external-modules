var ExternalModuleTests = {
    JS_UNIT_TESTING_PREFIX: 'js_unit_testing_prefix',
    BRANCHING_LOGIC_CHECK_FIELD_NAME: 'branching-logic-check-field-name',
    BRANCHING_LOGIC_AFFECTED_FIELD_NAME: 'branching-logic-affected-field-name',
    assertions: 0,

    run: function(outputElement){
        if(!window.ExternalModules || !ExternalModules.configsByPrefix){
            // Wait for them to be defined.
            $(function(){
                ExternalModuleTests.run(outputElement)
            })

            return
        }

        if(ExternalModules.configsByPrefix[this.JS_UNIT_TESTING_PREFIX]){
            throw Error('The js unit testing prefix is conflicting with an actual module!!!')
        }

        var modal = this.getModal()
        modal.data('module', this.JS_UNIT_TESTING_PREFIX)
        modal.show() // Required for the ':visible' checks to work

        try{
            ;['dropdown', 'textarea', 'rich-text', 'radio', 'button', 'custom', 'checkbox', 'text', 'some-invalid-type-that-defaults-to-text'].forEach(function(type){
                ExternalModuleTests.testDoBranching(type)
            })

            console.log('External Modules Framework JS Unit Tests completed successfully with ' + this.assertions + ' assertions')
		}
		catch(e){
			console.log('Unit Test', e)
			outputElement
				.html('<h3>A unit test failed! Use the stack trace in the browser console to find the line for the assertion that failed.</h3>')
				.show()
        }
        
        modal.hide()
    },

    testDoBranching: function(type){
        var assertDoBranching = function(expectedVisible, fieldValue, branchingLogic){
            ExternalModuleTests.assertDoBranching(type, expectedVisible, fieldValue, branchingLogic)
        }
        
        if(type === 'checkbox'){
            assertDoBranching(true, true, {
                value: true
            })
    
            assertDoBranching(true, false, {
                value: false
            })

            assertDoBranching(false, true, {
                value: false
            })

            assertDoBranching(false, false, {
                value: true
            })
    
            // Other assertions don't work on checkboxes
            return
        }

        assertDoBranching(true, 1, {
            value: 1
        })

        assertDoBranching(false, 1, {
            value: 2
        })

        assertDoBranching(true, 1, {
            value: 1,
            op: '='
        })

        assertDoBranching(false, 1, {
            value: 2,
            op: '='
        })

        assertDoBranching(true, 1, {
            type: "or",
            conditions: [
                { value: 1 },
                { value: 2 }
            ]
        })

        assertDoBranching(false, 1, {
            type: "or",
            conditions: [
                { value: 2 },
                { value: 3 }
            ]
        })

        assertDoBranching(true, 1, {
            type: "and",
            conditions: [
                { value: 1 },
                { value: 1 }
            ]
        })

        assertDoBranching(false, 1, {
            type: "and",
            conditions: [
                { value: 1 },
                { value: 2 }
            ]
        })

        if(type === 'radio'){
            // All assertions below this point don't work on radio buttons.
            return
        }

        assertDoBranching(true, 1, {
            value: 2,
            op: '!='
        })

        assertDoBranching(false, 2, {
            value: 2,
            op: '!='
        })

        assertDoBranching(true, 1, {
            value: 2,
            op: '<'
        })

        assertDoBranching(false, 2, {
            value: 2,
            op: '<'
        })

        assertDoBranching(true, 1, {
            value: 2,
            op: '<='
        })

        assertDoBranching(true, 2, {
            value: 2,
            op: '<='
        })

        assertDoBranching(true, 2, {
            value: 1,
            op: '>'
        })

        assertDoBranching(false, 2, {
            value: 2,
            op: '>'
        })

        assertDoBranching(true, 2, {
            value: 1,
            op: '>='
        })

        assertDoBranching(true, 2, {
            value: 2,
            op: '>='
        })

        assertDoBranching(true, 1, {
            value: 2,
            op: '<>'
        })

        assertDoBranching(false, 2, {
            value: 2,
            op: '<>'
        })
    },

    assertDoBranching: function(type, expectedVisible, fieldValue, branchingLogic){
        var assertDoBranchingForSettings = function(settings){
            ExternalModuleTests.assertDoBranchingForSettings(type, expectedVisible, fieldValue, settings)
        }
        
        assertDoBranchingForSettings(this.getTopLevelSettings(branchingLogic))
        assertDoBranchingForSettings(this.getTopToSubLevel1Settings(branchingLogic))
        assertDoBranchingForSettings(this.getTopToSubLevel2Settings(branchingLogic))
        
        // These tests should work once PR #275 is complete.
        // this.assertDoBranching_subToSubLevel1(expectedVisible, fieldValue, branchingLogic)
        // this.assertDoBranching_parentAlwaysHidden(expectedVisible, fieldValue, branchingLogic)
    },

    getTopLevelSettings: function(branchingLogic){
        return [
            {
                key: this.BRANCHING_LOGIC_CHECK_FIELD_NAME,
                name: "Some Field"
            },
            {
                key: this.BRANCHING_LOGIC_AFFECTED_FIELD_NAME,
                name: "Some Other Field",
                branchingLogic: branchingLogic
            }
        ]
    },

    getTopToSubLevel1Settings: function(branchingLogic){
        return [
            {
                key: this.BRANCHING_LOGIC_CHECK_FIELD_NAME,
                name: "Some Field"
            },
            {
                key: 'sub_settings_1',
                type: 'sub_settings',
                sub_settings: [
                    {
                        key: this.BRANCHING_LOGIC_AFFECTED_FIELD_NAME,
                        name: "Some Sub-Field",
                        branchingLogic: branchingLogic
                    }
                ]
            }
        ]
    },

    getTopToSubLevel2Settings: function(branchingLogic){
        return [
            {
                key: this.BRANCHING_LOGIC_CHECK_FIELD_NAME,
                name: "Field 1"
            },
            {
                key: 'sub_settings_1',
                type: 'sub_settings',
                sub_settings: [
                    {
                        type: 'sub_settings',
                        sub_settings: [
                            {
                                key: this.BRANCHING_LOGIC_AFFECTED_FIELD_NAME,
                                name: "Field 2",
                                branchingLogic: branchingLogic
                            }
                        ]
                    }
                ]
            }
        ]
    },

    assertDoBranching_subToSubLevel1: function(type, expectedVisible, fieldValue, branchingLogic){
        this.assertDoBranchingForSettings(type, expectedVisible, fieldValue, [
            {
                key: 'sub_settings_1',
                type: 'sub_settings',
                sub_settings: [
                    {
                        key: this.BRANCHING_LOGIC_CHECK_FIELD_NAME,
                        name: "Field 1"
                    },
                    {
                        key: 'sub_settings_2',
                        type: 'sub_settings',
                        sub_settings: [
                            {
                                key: this.BRANCHING_LOGIC_AFFECTED_FIELD_NAME,
                                name: "Field 2",
                                branchingLogic: branchingLogic
                            }
                        ]
                    }
                ]
            }
        ])
    },

    assertDoBranching_parentAlwaysHidden: function(type, expectedVisible, fieldValue, branchingLogic){
        this.assertDoBranchingForSettings(type, false, fieldValue, [
            {
                key: this.BRANCHING_LOGIC_CHECK_FIELD_NAME,
                name: "Field 1"
            },
            {
                key: 'sub_settings_1',
                type: 'sub_settings',
                branchingLogic: {
                    value: null,
                },
                sub_settings: [
                    {
                        key: 'sub_settings_2',
                        type: 'sub_settings',
                        sub_settings: [
                            {
                                key: this.BRANCHING_LOGIC_AFFECTED_FIELD_NAME,
                                name: "Field 3",
                                branchingLogic: branchingLogic
                            }
                        ]
                    }
                ]
            }
        ])
    },

    addFieldNameToConditions: function(branchingLogic){
       var conditions = branchingLogic.conditions
        if(conditions === undefined){
            conditions = [branchingLogic]
        }
        
        conditions.forEach(function(condition){
            condition.field = ExternalModuleTests.BRANCHING_LOGIC_CHECK_FIELD_NAME
        })
    },

    addFieldNameToAllBranchingLogic: function(settings, type){
        var checkFieldFound = false
        var affectedFieldFound = false

        $.each(settings, function(index, setting){
            if(setting.key === ExternalModuleTests.BRANCHING_LOGIC_CHECK_FIELD_NAME){
                checkFieldFound = true
            }
            else if(setting.key === ExternalModuleTests.BRANCHING_LOGIC_AFFECTED_FIELD_NAME){
                affectedFieldFound = true
            }

            if(!setting.type){
                setting.type = type
                
                if(type === 'radio'){
                    setting.choices = [{
                        name: "Some choice",
                        value: "Doesn't matter, will get overwritten"
                    }]
                }
                else if(type === 'button'){
                    setting.url = "Doesn't matter"
                }
            }

            if(setting.branchingLogic){
                ExternalModuleTests.addFieldNameToConditions(setting.branchingLogic)
            }

            if(setting.type === 'sub_settings'){
                // Run all tests against repeatable subsettings (the more complex case).
                setting.repeatable = true

                ExternalModuleTests.addFieldNameToAllBranchingLogic(setting.sub_settings, type)
            }
        })

        return [!checkFieldFound, !affectedFieldFound]
    },

    getModal: function(){
        return $('#external-modules-configure-modal')
    },

    assertDoBranchingForSettings: function(type, expectedVisible, fieldValue, settings){
        // Clone the settings object, since we'll be modifying it and don't want to affect other tests.
        settings = JSON.parse(JSON.stringify(settings));

        ExternalModules.configsByPrefix[this.JS_UNIT_TESTING_PREFIX] = {
            // Project settings are expected even if they're empty.
            'project-settings': [],

            // We're not defining ExternalModules.PID, so it's easier to test with system settings.
            'system-settings': settings
        }

        var modal = this.getModal()

        // A const is OK here because we don't run tests in IE currently.
        const [isCheckedFieldInSubSetting, isAffectedFieldInSubSetting] = this.addFieldNameToAllBranchingLogic(settings, type)

        modal.find('tbody').html(ExternalModules.Settings.prototype.getSettingRows(settings, {}))
        ExternalModules.Settings.prototype.initializeSettingsFields()

        // Add a second instance to all repeatables
        modal.find('button.external-modules-add-instance').click()
        
        var getField = function(name, instance){
            if(!instance){
                instance = 1
            }

            var field = modal.find('[name^=' + name + ']')[instance-1]

            if(!field){
                throw new Error('Instance ' + instance + ' of the "' + name + '" field was not found!')
            }

            return $(field)
        }

        var setupSetting = function(name, value, instance){
            var field = getField(name, instance)

            if(type === 'dropdown'){
                field.append('<option value="' + value + '" selected>')
            }

            field.val(value)

            if(type === 'checkbox'){
                if(value){
                    field.prop('checked', true)
                }
                else{
                    field.prop('checked', false)
                }
            }
            else if(type === 'radio'){
                field.prop('checked', true)
            }
            else if(value !== undefined && field.val() != value){
                throw new Error('Expected field value of "' + value + '" but found "' + field.val() + '"!')
            }
        }
        
        if(isCheckedFieldInSubSetting){
            setupSetting(this.BRANCHING_LOGIC_CHECK_FIELD_NAME, fieldValue, 1)
            setupSetting(this.BRANCHING_LOGIC_CHECK_FIELD_NAME, undefined, 2)
        }
        else{
            setupSetting(this.BRANCHING_LOGIC_CHECK_FIELD_NAME, fieldValue)
        }
        
        if(isAffectedFieldInSubSetting){
            setupSetting(this.BRANCHING_LOGIC_AFFECTED_FIELD_NAME, undefined, 1)
            setupSetting(this.BRANCHING_LOGIC_AFFECTED_FIELD_NAME, undefined, 2)
        }
        else{
            setupSetting(this.BRANCHING_LOGIC_AFFECTED_FIELD_NAME)
        }
        
        ExternalModules.Settings.prototype.doBranching()

        var assert = function(expectedVisible, instance){
            var actuallyVisible = getField(ExternalModuleTests.BRANCHING_LOGIC_AFFECTED_FIELD_NAME, instance).is(':visible')
            ExternalModuleTests.assert(actuallyVisible === expectedVisible)
        }

        if(isAffectedFieldInSubSetting){
            assert(expectedVisible, 1)

            if(isCheckedFieldInSubSetting){
                assert(false, 2)
            }
            else{
                assert(expectedVisible, 2)
            }
        }
        else{
            assert(expectedVisible)
        }
	},

	assert: function(assertion){
        this.assertions++

		if(!assertion){
			throw new Error('Assertion failed!')
        }
	}
}

if(location.hostname === 'localhost' && document.currentScript){
    // This is a modern browser.  Run tests.
    ExternalModuleTests.run($(document.currentScript.parentElement))
}