<?php

namespace Cognesy\Instructor\Xml;

class XmlFunctionCallUtils
{
    public function preambule() : string {
        $lines = [
            'In this environment you have access to a set of tools you can use to answer the user\'s question.',
            'You may call them like this:',
            '<function_calls>',
            '<invoke>',
            '<tool_name>$TOOL_NAME</tool_name>',
            '<parameters>',
            '<$PARAMETER_NAME>$PARAMETER_VALUE</$PARAMETER_NAME>',
            '...',
            '</parameters>',
            '</invoke>',
            '</function_calls>',
        ];
    }

    public function tools(array $tools) : string {
        $lines = [
            '<tools>',
            '</tools>',
        ];
    }

    public function tool(string $name, string $description, array $parameters) : string {
        $lines = [
            '<tool_description>',
            '<tool_name>get_weather</tool_name>',
            '<description>',
            //'Retrieves the current weather for a specified location.',
            //'Returns a dictionary with two fields:',
            //'- temperature: float, the current temperature in Fahrenheit',
            //'- conditions: string, a brief description of the current weather conditions',
            //'Raises ValueError if the provided location cannot be found.',
            '</description>',
            $this->parameters($parameters),
            '</tool_description>',
        ];
    }

    public function calls(array $tools) : string {
        $lines = [
            '<function_calls>',
            '<invoke>',
            '<tool_name>function_name</tool_name>',
            '<parameters>',
            '<param1>value1</param1>',
            '<param2>value2</param2>',
            '</parameters>',
            '</invoke>',
            '<invoke>',
            '<tool_name>function_name</tool_name>',
            '<parameters>',
            '<param1>value1</param1>',
            '<param2>value2</param2>',
            '</parameters>',
            '</invoke>',
            '</function_calls>',
        ];
    }

    public function callResuls(array $tools) : string {
        $lines = [
            '<function_results>',
            '<result>',
            '<tool_name>function_name</tool_name>',
            '<stdout>',
            'function result goes here',
            '</stdout>',
            '</result>',
            '<result>',
            '<tool_name>function_name</tool_name>',
            '<stdout>',
            'function result goes here',
            '</stdout>',
            '</result>',
            '</function_results>',
        ];
    }

    public function callError() : string {
        $lines = [
            '<function_results>',
            '<error>',
            'error message goes here',
            '</error>',
            '</function_results>',
        ];
    }

    private function parameters(array $parameters)
    {
        $lines = [
            '<parameters>',
            '<parameter>',
            '<name>location</name>',
            '<type>string</type>',
            '<description>The city and state, e.g. San Francisco, CA</description>',
            '</parameter>',
            '</parameters>',
        ];
    }

    private function simpleParameter(string $name, string $type, string $description) : string {
        $lines = [
            '<parameter>',
            '<name>'.$name.'</name>',
            '<type>'.$type.'</type>',
            '<description>'.$description.'</description>',
            '</parameter>',
        ];
    }

    private function arrayParameter(string $name, string $type, string $description, string $items) : string {
        $lines = [
            '<parameter>',
            '<name>'.$name.'</name>',
            '<type>array</type>',
            '<description>'.$description.'</description>',
            '<items>'.$items.'</items>',
            '</parameter>',
        ];
    }

    private function objectParameter(string $name, string $type, string $description, array $properties) : string {
        $lines = [
            '<parameter>',
            '<name>'.$name.'</name>',
            '<type>object</type>',
            '<description>'.$description.'</description>',
            $this->objectProperties($properties),
            '</parameter>',
        ];
    }

    private function objectProperties(array $properties)
    {
        $lines = [
            '<properties>',
            '<property>',
            '<name>temperature</name>',
            '<type>float</type>',
            '<description>The current temperature in Fahrenheit</description>',
            '</property>',
            '<property>',
            '<name>conditions</name>',
            '<type>string</type>',
            '<description>A brief description of the current weather conditions</description>',
            '</property>',
            '</properties>',
        ];
    }
}
