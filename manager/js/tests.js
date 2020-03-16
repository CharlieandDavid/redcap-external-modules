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
        this.assertDoBranching_subSettingLevel1(expectedVisible, fieldValue, branchingLogic)
    },

    assertDoBranching_topLevel: function(expectedVisible, fieldValue, branchingLogic){
        var settings = [
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

        this.assertDoBranchingForSettings(expectedVisible, fieldValue, settings, false)
    },

    assertDoBranching_subSettingLevel1: function(expectedVisible, fieldValue, branchingLogic){
        var settings = [
            {
                key: this.BRANCHING_LOGIC_CHECK_FIELD_NAME,
                name: "Some Field"
            },
            {
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

        this.assertDoBranchingForSettings(expectedVisible, fieldValue, settings, true)
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

    addFieldNameToAllBranchingLogic: function(settings){
        for(var key in settings){
            var setting = settings[key]
            if(setting.branchingLogic){
                this.addFieldNameToConditions(setting.branchingLogic)
            }

            if(setting.type === 'sub_settings'){
                this.addFieldNameToAllBranchingLogic(setting.sub_settings)
            }
        }
    },

    assertDoBranchingForSettings: function(expectedVisible, fieldValue, settings, isSubSetting){
        var getInstanceInputName = function(field, instance){
            return field + '____' + instance
        }

        var fieldNames = []
        if(isSubSetting){
            fieldNames.push(getInstanceInputName(this.BRANCHING_LOGIC_AFFECTED_FIELD_NAME, 1))
            fieldNames.push(getInstanceInputName(this.BRANCHING_LOGIC_AFFECTED_FIELD_NAME, 2))
        }
        else{
            fieldNames.push(this.BRANCHING_LOGIC_AFFECTED_FIELD_NAME)
        }

        this.addFieldNameToAllBranchingLogic(settings)
        
        ExternalModules.configsByPrefix[this.JS_UNIT_TESTING_PREFIX] = {
            // We're not defining ExternalModules.PID, so it's easier to test with system settings.
            'system-settings': settings
        }

        var modal = $('#external-modules-configure-modal')
        var setupSetting = function(field, value, instance){
            var name = field
            if(instance !== undefined){
                name = getInstanceInputName(field, instance)
            }

            modal.find('input[name=' + name + ']').remove() // remove inputs from other assertions
            modal.append('<input field="' + field + '" name="' + name + '" value="' + value + '">\n')
        }
        
        setupSetting(this.BRANCHING_LOGIC_CHECK_FIELD_NAME, fieldValue)
        setupSetting(this.BRANCHING_LOGIC_AFFECTED_FIELD_NAME)
        setupSetting(this.BRANCHING_LOGIC_AFFECTED_FIELD_NAME, null, 1)
        setupSetting(this.BRANCHING_LOGIC_AFFECTED_FIELD_NAME, null, 2)
        
        ExternalModules.Settings.prototype.doBranching()

        var assert = function(fieldName){
            var style = modal.find('[name='+fieldName+']').attr('style')
            var actuallyVisible = style !== 'display: none;'
    
            ExternalModuleTests.assert(actuallyVisible === expectedVisible)
        }

        $.each(fieldNames, function(index, fieldName){
            assert(fieldName)
        })
	},

	assert: function(assertion){
        this.assertions++

		if(!assertion){
			throw new Error('Assertion failed!')
        }
	}
}

if(document.currentScript){
    // This is a modern browser.  Run tests.
    ExternalModuleTests.run($(document.currentScript.parentElement))
}