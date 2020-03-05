var ExternalModuleTests = {
    JS_UNIT_TESTING_PREFIX: 'js_unit_testing_prefix',

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
        var fieldName1 = 'some_field'
        var fieldName2 = 'some_other_field'
        var fieldName3 = 'some_sub_field'
        var subSettingsParentFieldName = 'sub_settings_parent'

        var getInstanceInputName = function(field, instance){
            return field + '____' + instance
        }

        var fieldName3instance1 = getInstanceInputName(fieldName3, 1)
        var fieldName3instance2 = getInstanceInputName(fieldName3, 2)

        var conditions = branchingLogic.conditions
        if(conditions === undefined){
            conditions = [branchingLogic]
        }
        
        conditions.forEach(function(condition){
            condition.field = fieldName1
        })

        var settings = [
            {
                key: fieldName1,
                name: "Some Field"
            },
            {
                key: fieldName2,
                name: "Some Other Field",
                branchingLogic: branchingLogic
            },
            {
                key: subSettingsParentFieldName,
                type: 'sub_settings',
                sub_settings: [
                    {
                        key: fieldName3,
                        name: "Some Sub-Field",
                        branchingLogic: branchingLogic
                    }
                ]
            }
        ]

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
        
        setupSetting(fieldName1, fieldValue)
        setupSetting(fieldName2)
        setupSetting(subSettingsParentFieldName)
        setupSetting(fieldName3, null, 1)
        setupSetting(fieldName3, null, 2)
        
        ExternalModules.Settings.prototype.doBranching()

        var assert = function(fieldName){
            var style = modal.find('[name='+fieldName+']').attr('style')
            var actuallyVisible = style !== 'display: none;'
    
            ExternalModuleTests.assert(actuallyVisible === expectedVisible)
        }

        assert(fieldName2)
        assert(fieldName3instance1)
        assert(fieldName3instance2)
	},

	assert: function(assertion){
		if(!assertion){
			throw new Error('Assertion failed!')
		}
	}
}

if(document.currentScript){
    // This is a modern browser.  Run tests.
    ExternalModuleTests.run($(document.currentScript.parentElement))
}