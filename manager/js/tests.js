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
            value: 2,
            op: '<',
        })

        this.assertDoBranching(false, 2, {
            value: 2,
            op: '<',
        })

        this.assertDoBranching(true, 1, {
            value: 2,
            op: '<=',
        })

        this.assertDoBranching(true, 2, {
            value: 2,
            op: '<=',
        })

        this.assertDoBranching(true, 2, {
            value: 1,
            op: '>',
        })

        this.assertDoBranching(false, 2, {
            value: 2,
            op: '>',
        })

        this.assertDoBranching(true, 2, {
            value: 1,
            op: '>=',
        })

        this.assertDoBranching(true, 2, {
            value: 2,
            op: '>=',
        })

        this.assertDoBranching(true, 1, {
            value: 2,
            op: '<>',
        })

        this.assertDoBranching(false, 2, {
            value: 2,
            op: '<>',
        })

        this.assertDoBranching(true, 1, {
            value: 2,
            op: '!=',
        })

        this.assertDoBranching(false, 2, {
            value: 2,
            op: '!=',
        })
    },

    assertDoBranching: function(expectedVisible, fieldValue, branchingLogic){
        var fieldName1 = 'some_field_1'
        var fieldName2 = 'some_other_field'

        branchingLogic.field = fieldName1

        var settings = [
            {
                key: fieldName1,
                name: "Some Field",
                value: fieldValue
            },
            {
                key: fieldName2,
                name: "Some Other Field",
                branchingLogic: branchingLogic
            }
        ]

        ExternalModules.configsByPrefix[this.JS_UNIT_TESTING_PREFIX] = {
            // We're not defining ExternalModules.PID, so it's easier to test with system settings.
            'system-settings': settings
        }
        
        var modal = $('#external-modules-configure-modal')
        settings.forEach(function(setting){
            modal.find('input[name=' + setting.key + ']').remove() // remove inputs from other assertions
            modal.append('<input field="' + setting.key + '" name="' + setting.key + '" value="' + setting.value + '">')
        })
        
        ExternalModules.Settings.prototype.doBranching()

        var style = modal.find('[field='+fieldName2+']').attr('style')
        var actuallyVisible = style !== 'display: none;'

        this.assert(actuallyVisible === expectedVisible)
	},

	assert: function(assertion){
		if(!assertion){
			throw new Error('Assertion failed!')
		}
	}
}

ExternalModuleTests.run($(document.currentScript.parentElement))