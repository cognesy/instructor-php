<?php
return [
    [
        'input' => 'Acme Insurance project to implement SalesTech CRM solution is currently in RED status due to delayed delivery of document production system, led by 3rd party vendor - Alfatech. Customer (Acme) is discussing the resolution with the vendor. Production deployment plan has been finalized on Aug 15th and awaiting customer approval.',
        'output' => [
            "type" => "object",
            "title" => "sequenceOfProjectEvent",
            "description" => "A sequence of ProjectEvent",
            "properties" => [
                "list" => [
                    [
                        "date" => "2021-09-01",
                        "title" => "Absorbing delay by deploying extra resources",
                        "description" => "System integrator (SysCorp) are working to absorb some of the delay by deploying extra resources to speed up development when the doc production is done.",
                        "type" => "action",
                        "status" => "open",
                        "stakeholders" => [
                            [
                                "name" => "SysCorp",
                                "role" => "system integrator",
                                "details" => "System integrator",
                            ],
                        ],
                    ],
                    [
                        "date" => "2021-08-15",
                        "title" => "Finalization of production deployment plan",
                        "description" => "Production deployment plan has been finalized on Aug 15th and awaiting customer approval.",
                        "type" => "progress",
                        "status" => "open",
                        "stakeholders" => [
                            [
                                "name" => "Acme",
                                "role" => "customer",
                                "details" => "Customer",
                            ],
                        ],
                    ],
                ],
            ]
        ]
    ]
];
