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

        var modal = this.getModal()
        modal.show() // Required for the ':visible' checks to work

        try{
            this.testDoBranching()
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

    testDoBranching: function(){
        if(ExternalModules.configsByPrefix[this.JS_UNIT_TESTING_PREFIX]){
            throw Error('The js unit testing prefix is conflicting with an actual module!!!')
        }

        $('#external-modules-configure-modal').data('module', this.JS_UNIT_TESTING_PREFIX)

        this.assertDoBranching(true, 1, {
            value: 1
        })

        this.assertDoBranching(false, 1, {
            value: 2
        })

        this.assertDoBranching(true, 1, {
            value: 1,
            op: '='
        })

        this.assertDoBranching(false, 1, {
            value: 2,
            op: '='
        })

        this.assertDoBranching(true, 1, {
            value: 2,
            op: '<'
        })

        this.assertDoBranching(false, 2, {
            value: 2,
            op: '<'
        })

        this.assertDoBranching(true, 1, {
            value: 2,
            op: '<='
        })

        this.assertDoBranching(true, 2, {
            value: 2,
            op: '<='
        })

        this.assertDoBranching(true, 2, {
            value: 1,
            op: '>'
        })

        this.assertDoBranching(false, 2, {
            value: 2,
            op: '>'
        })

        this.assertDoBranching(true, 2, {
            value: 1,
            op: '>='
        })

        this.assertDoBranching(true, 2, {
            value: 2,
            op: '>='
        })

        this.assertDoBranching(true, 1, {
            value: 2,
            op: '<>'
        })

        this.assertDoBranching(false, 2, {
            value: 2,
            op: '<>'
        })

        this.assertDoBranching(true, 1, {
            value: 2,
            op: '!='
        })

        this.assertDoBranching(false, 2, {
            value: 2,
            op: '!='
        })

        this.assertDoBranching(true, 1, {
            type: "or",
            conditions: [
                { value: 1 },
                { value: 2 }
            ]
        })

        this.assertDoBranching(false, 1, {
            type: "or",
            conditions: [
                { value: 2 },
                { value: 3 }
            ]
        })

        this.assertDoBranching(true, 1, {
            type: "and",
            conditions: [
                { value: 1 },
                { value: 1 }
            ]
        })

        this.assertDoBranching(false, 1, {
            type: "and",
            conditions: [
                { value: 1 },
                { value: 2 }
            ]
        })
    },

    assertDoBranching: function(expectedVisible, fieldValue, branchingLogic){
        this.assertDoBranching_topLevel(expectedVisible, fieldValue, branchingLogic)
        this.assertDoBranching_topToSubLevel1(expectedVisible, fieldValue, branchingLogic)
        this.assertDoBranching_topToSubLevel2(expectedVisible, fieldValue, branchingLogic)
        
        // These tests should work once PR #275 is complete.
        // this.assertDoBranching_subToSubLevel1(expectedVisible, fieldValue, branchingLogic)
        // this.assertDoBranching_parentAlwaysHidden(expectedVisible, fieldValue, branchingLogic)
    },

    assertDoBranching_topLevel: function(expectedVisible, fieldValue, branchingLogic){
        this.assertDoBranchingForSettings(expectedVisible, fieldValue, [
            {
                key: this.BRANCHING_LOGIC_CHECK_FIELD_NAME,
                name: "Some Field"
            },
            {
                key: this.BRANCHING_LOGIC_AFFECTED_FIELD_NAME,
                name: "Some Other Field",
                branchingLogic: branchingLogic
            }
        ])
    },

    assertDoBranching_topToSubLevel1: function(expectedVisible, fieldValue, branchingLogic){
        this.assertDoBranchingForSettings(expectedVisible, fieldValue, [
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
        ])
    },

    assertDoBranching_topToSubLevel2: function(expectedVisible, fieldValue, branchingLogic){
        this.assertDoBranchingForSettings(expectedVisible, fieldValue, [
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
        ])
    },

    assertDoBranching_subToSubLevel1: function(expectedVisible, fieldValue, branchingLogic){
        this.assertDoBranchingForSettings(expectedVisible, fieldValue, [
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

    assertDoBranching_parentAlwaysHidden: function(expectedVisible, fieldValue, branchingLogic){
        this.assertDoBranchingForSettings(false, fieldValue, [
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

    assertDoBranchingForSettings: function(expectedVisible, fieldValue, settings){
        // TODO - These types need to be fixed: 'dropdown', 'radio', 'button', 'checkbox'
        ;['textarea', 'rich-text', 'custom', 'text', 'some-invalid-type-that-defaults-to-text'].forEach(function(type){
            ExternalModuleTests.assertDoBranchingForSettingsAndType(expectedVisible, fieldValue, settings, type)
        })
    },

    assertDoBranchingForSettingsAndType: function(expectedVisible, fieldValue, settings, type){
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
            getField(name, instance).val(value)
        }
        
        if(isCheckedFieldInSubSetting){
            setupSetting(this.BRANCHING_LOGIC_CHECK_FIELD_NAME, fieldValue, 1)
            setupSetting(this.BRANCHING_LOGIC_CHECK_FIELD_NAME, null, 2)
        }
        else{
            setupSetting(this.BRANCHING_LOGIC_CHECK_FIELD_NAME, fieldValue)
        }
        
        if(isAffectedFieldInSubSetting){
            setupSetting(this.BRANCHING_LOGIC_AFFECTED_FIELD_NAME, null, 1)
            setupSetting(this.BRANCHING_LOGIC_AFFECTED_FIELD_NAME, null, 2)
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