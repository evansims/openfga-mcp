# OPENFGA_DOCS Documentation

> Compiled from: https://github.com/openfga/openfga.dev
> Generated: 2025-08-03 22:23:01 UTC

---


<!-- Source: openfga/openfga.dev/docs/content/best-practices/adoption-patterns.mdx -->

---
title: Adoption Patterns
slug: /best-practices/adoption-patterns
description: Describe different ways FGA can be adopted in an organization
sidebar_position: 1
---
import {
  ProductName,
  ProductNameFormat,
  RelatedSection,
} from '@components/Docs';

### <ProductName format={ProductNameFormat.ShortForm}/> Adoption Patterns

This document outlines key implementation patterns for adopting  <ProductName format={ProductNameFormat.ShortForm}/> in your organization.

#### Starting with coarse-grained access control

When evaluating this solution, many companies start by replicating their existing permissions structure before moving to more granular controls. For example, if you're using Role-Based Access Control (RBAC) in a B2B scenario, you might start with a simple model:

```dsl.openfga
model 
  schema 1.1

  type user
  type organization
    relations
      define admin: [user]
      define member: [user]
      # .. add additional organization roles

      # map permissions to organization roles 
      define can_add_member: admin
      define can_delete_member: admin
      define can_view_member: admin or member
      define can_add_resource: admin or member
```

You can define any number of roles for the organization type and then define the permissions based on those roles. You can then check if users have a specific permission at the organization level by calling the Check API on the organization object: 

```
Check(user: "user:anne", relation: "can_add_member", object: "organization:acme") 
```

A better implementation is to define the application's resource types in the model (e.g. documents, projects, insurance policies, bank accounts, etc):


```dsl.openfga
model 
  schema 1.1

  type user
  type organization
    relations
      define admin: [user]
      define member: [user]

      define can_add_member: admin
      define can_delete_member: admin
      define can_view_member: admin or member
      define can_add_resource: admin or member

   type resource
     relations
       define organization: [organization]

      # map resource permissions to organization roles
       define can_delete_resource: admin from organization or member from organization
       define can_view_resource: admin from organization or member from organization      

```

In this case, you'll need to write tuples that establish the relationship between resource instances and organizations, or use Contextual Tuples to specify them, e.g:

```
user: organization:acme
relation: organization
object: resource:root
```

In this case, the Check() call will be at the resource level, for example:

```
Check(user: "user:anne", relation: "can_view_resource", object: "resource:root") 
```

The main advantage of this approach is that your APIs will be checking permissions at the proper level. If you later want to evolve your authorization model to be more fine grained, you won't need to change your app. For example, you can add fine grained access permissions at the resource level, and your authorization check won't change:

```
   type resource
     relations
       define organization: [organization]
       define owner: [user]
       define viewer: [user]

      # map resource permissions to organization roles
       define can_delete_resource: admin from organization or member from organization or owner
       define can_view_resource: admin from organization or member from organization or owner or viewer
```

#### Provide request-level data

One of the advantages of the Zanzibar/<ProductName format={ProductNameFormat.ShortForm}/> approach is that all the data you need to make authorization decisions is stored in a centralized database. That greatly simplifies how application implement access control. Applications do not need to retrieve all the required data before invoking an authorization service.

However, writing the data to the centralized store adds implementation complexity. You need to implement a data pipeline that makes sure the data is always up to date.

<ProductName format={ProductNameFormat.ShortForm}/> provides a feature called [Contextual Tuples](https://github.com/openfga/openfga.dev/blob/main/../interacting/contextual-tuples.mdx) that allows sending the required data as part of each authorization request instead of storing it on the <ProductName format={ProductNameFormat.ShortForm}/> database. Overusing this feature has many drawbacks, as you are now adding additional complexity and latency around collecting the data, and you are not benefiting from using <ProductName format={ProductNameFormat.ShortForm}/> as intended. However, implementing a hybrid approach can make sense in many scenarios and can also be a helpful tool at the start when you are transitioning into a more OpenFGA tailored approach.

When the data is already available to the calling API, sending it as a contextual tuple is very simple. A common use case is you have data in [your access tokens](https://github.com/openfga/openfga.dev/blob/main/../modeling/token-claims-contextual-tuples.mdx) (for example, roles/groups claims). Instead of synchronizing groups/roles relations to <ProductName format={ProductNameFormat.ShortForm}/>, you can send those as contextual tuples.

When the data is not already, you will need to retrieve it. This is what you need to do if you are implementing pure Attribute Access Control. You'd retrieve the data and send it to the authorization policy engine. You can do the same with <ProductName format={ProductNameFormat.ShortForm}/> using Contextual Tuples.

You'll need to make the trade-off between writing the data to <ProductName format={ProductNameFormat.ShortForm}/> so it's always available for any authorization request, or requesting it before making an authorization check.

We've seen companies successfully following a hybrid approach, starting by synchronizing the data that's easy first and providing the rest as contextual tuples. As their implementation matures, they implement more synchronization processes and stop sending the contextual tuples.

#### Use <ProductName format={ProductNameFormat.ShortForm}/> to enrich JWTs

Once you have your authorization model and data set up, you can start making authorization checks from your application. The preferred way is to perform a [Check()](https://github.com/openfga/openfga.dev/blob/main/../getting-started/perform-check.mdx) call.

However, you might have a large set of APIs that are already making authorization checks using JWTs. Changing those applications can be a significant investment. Even if JWTs have several drawbacks compared to making FGA API calls, it can be reasonable to first start by using <ProductName format={ProductNameFormat.ShortForm}/> to generate the claims that are stored in JWTs, while the applications keep using those claims to make authorization decisions.

Over time, you'll migrate the applications and APIs to use authorization check instead.

Authentication services usually provide a way to enrich access tokens during the authorization flow. You can see an example on how to do it with Auth0 [here](https://auth0.com/blog/enrich-auth0-access-tokens-with-auth0-fga-data/).

For example, if you want to include in the access token the organizations that a user can log-in to, based on the following model:

```
  type user
  type organization
    relations
      define member: [user]
```

You can call `ListObjects(type:"organization", relation:"member", user: "user:xxx")` and include those.

#### Promoting Organization-Wide Adoption

To introduce <ProductName format={ProductNameFormat.ShortForm}/> in a large company, it's recommended that you identify a problem where the additional enables quickly delivering business value to customers. It can be a new project, a new module, a new feature. Using <ProductName format={ProductNameFormat.ShortForm}/> for such a project can be an easier decision. Once an implementation is successful, you can try influencing the rest of the organization to adopt it.

However, influencing the decision makers of a large organization can be hard. Each team has their own internal roadmaps and not all of the teams will see value in implementing a new authorization system. Migration can be seen as a tech-debt project instead of a business-value-driven one. 

The can take advantage of the following capabilities to simplify adoption by multiple teams:

- [Modular Models](https://github.com/openfga/openfga.dev/blob/main/../modeling/modular-models.mdx) enable each team to independently evolve their authorization policies without relying on a central team.
- [Access Control](https://github.com/openfga/openfga.dev/blob/main/../getting-started/setup-openfga/access-control.mdx) allows you to issue different credentials for each application, with permissions that ensure that each credential can only write data to the types defined in the Modules they own.

#### Domain-Specific Authorization Server

Some companies decide to wrap <ProductName format={ProductNameFormat.ShortForm}/> with their own authorization service. They decide to do this for multiple reasons:

- Sometimes they already have a centralized service, and it's easy to replace it with another without changing the calling applications.
- It can simplify internal adoption by providing domain-specific APIs. Instead of calling `write` or `check`, applications can call a `/share-document` endpoint or a `/can-view-document` one. Each team does not need to learn the <ProductName format={ProductNameFormat.ShortForm}/> API.
- If they are using Contextual Tuples, they can keep the logic to retrieve additional data to send to <ProductName format={ProductNameFormat.ShortForm}/> in a single service.
- They only need to provide <ProductName format={ProductNameFormat.ShortForm}/> configuration data like Store ID and Model ID in a single service.

On the other hand, adding another service increases latency, adds additional complexity and would make the teams less likely to find help from existing public OpenFGA documentation and resources.

#### Shadowing the <ProductName format={ProductNameFormat.ShortForm}/> API

When migrating from an existing authorization system to <ProductName format={ProductNameFormat.ShortForm}/>, it's recommended to first run both systems in parallel, with <ProductName format={ProductNameFormat.ShortForm}/> in "shadow mode". This means that while the existing system continues to make the actual authorization decisions, you also make calls to <ProductName format={ProductNameFormat.ShortForm}/> asynchronously and compare the results.

This approach has several benefits:

- You can validate that your authorization model and relationship tuples are correctly configured before switching to <ProductName format={ProductNameFormat.ShortForm}/>.
- You can measure the performance impact of adding <ProductName format={ProductNameFormat.ShortForm}/> calls to your application.
- You can identify edge cases where the <ProductName format={ProductNameFormat.ShortForm}/> results differ from your existing system.
- You can gradually build confidence in the <ProductName format={ProductNameFormat.ShortForm}/> implementation.

To implement shadow mode:

1. Configure your application to make authorization checks against both systems
2. Log any discrepancies between the two systems
3. Analyze the logs to identify and fix any issues
4. Once confident in the results, switch to using <ProductName format={ProductNameFormat.ShortForm}/> as the source of truth. The same approach of shallow checks when [migrating between models](https://github.com/openfga/openfga.dev/blob/main/../getting-started/immutable-models.mdx#potential-use-cases).

This pattern is particularly useful for critical systems where authorization errors could have significant impact.

#### Related Sections

<RelatedSection
  description="Check out these related resources for more information about adopting OpenFGA"
  relatedLinks={[
    {
      title: 'Running OpenFGA in Production',
      description: 'Learn about best practices for running OpenFGA in production environments.',
      link: './running-in-production',
    },
    {
      title: 'Modular Authorization Models',
      description: 'Learn how to break down your authorization model into modules.',
      link: './../modeling/modular-models',
    }
  ]}
/>



<!-- End of openfga/openfga.dev/docs/content/best-practices/adoption-patterns.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/best-practices/modeling-roles.mdx -->

---
title: Modeling roles
slug: /best-practices/modeling-roles
description: Various ways of modeling static and dynamic roles in FGA - both coarse and fine-grained.
sidebar_position: 1
---
import {
  ProductName,
  ProductNameFormat,
  RelatedSection,
} from '@components/Docs';

### Modeling Roles

Roles are a common way to group users and assign permissions to those groups. They can be used to simplify permission management, especially in larger systems where many users have similar access needs.

In this guide, we'll explore common approaches to modeling roles with <ProductName format={ProductNameFormat.ShortForm}/>.

#### When to Use Each Approach

Before diving into implementation details, here's a quick guide to help you choose the right approach:

| Approach                      | Best For                           | Complexity | Flexibility | Example                                                        
|-------------------------------|------------------------------------|------------|-------------|--------------------------------------------------------------------------------------------------------------------|
| **Relations as Roles**        | Static, predefined roles           | Low        | Low         | In all instances, company admins can view project information.                                                     |
| **Simple User-Defined Roles** | User-defined roles at org level    | Medium     | Medium      | Company Acme creates an "Auditor" role that is configured to view project information for all projects.            |
| **Role Assignments**          | Instance-specific role assignments | High       | High        | In Company Acme, Anne can be a custom Auditor role for Projects 1 and 5, but Beth can be an Auditor on Project 3.  |

#### Approach 1: Relations as Roles

The simplest way to implement roles is to use directly assignable relations. They work well for roles that always exist and can be defined at development-time. Adding relations is straightforward, and you do not need to add roles very frequently. If roles are static, this is always the preferred approach.

##### Example: Organization Admin Role

In the model below, we define an `admin` role at the organization level. Admins can edit billing details and create projects.

```dsl.openfga
model
  schema 1.1

type user

type organization
  relations
    define admin: [user]
    define can_create_project: admin
    define can_edit_billing_details: admin
```

##### Adding Users to Roles

To add users to the admin role, create a tuple like:

```yaml
- user: user:anne
  relation: admin
  object: organization:acme
```

##### Extending with Additional Roles

If later you need to add a `project_admin` role with permissions to view/edit projects, the model evolves to:

```dsl.openfga
model
  schema 1.1

type user

type organization
  relations
    define admin: [user]
    define project_admin: [user]  # new role

    # existing permissions
    define can_edit_billing_details: admin 
    define can_create_project: admin or project_admin

    # new permissions for project admins
    define can_edit_project: admin or project_admin
    define can_view_project: admin or project_admin
```

##### Pros and Cons

**✅ Advantages:**
- Simple to implement and understand
- Fast evaluation performance
- Clear authorization policies
- No additional tuples needed when adding permissions
- Role permissions are straightforward to change, regardless of scale

**❌ Disadvantages:**
- Roles must be predefined in the model
- Not suitable for user-defined roles

---

#### Approach 2: Simple User-Defined Roles

Many applications require the flexibility for end-users to define their own custom roles, in addition to any pre-defined roles. This approach enables organizations to tailor permissions to their specific needs.

##### Example: Custom Project Admin Role

With the following model, your application can support both static roles and user-defined roles:

```dsl.openfga
model
  schema 1.1

type user

type role
  relations
    define assignee: [user]

type organization
  relations
    define admin: [user]  # static role

    # permissions can be assigned to custom roles or static roles
    define can_create_project: [role#assignee] or admin 
    define can_edit_project: [role#assignee] or admin 
```

##### Setting Up Custom Roles

1. **Define role permissions** by creating tuples that grant the role-specific permissions:

```yaml
- user: role:acme-project-admin#assignee
  relation: can_create_project
  object: organization:acme

- user: role:acme-project-admin#assignee
  relation: can_edit_project
  object: organization:acme
```

2. **Assign users to the role**:

```yaml
- user: user:anne
  relation: assignee
  object: role:acme-project-admin
```

##### Adding New Permissions

When you add new permissions to your model, existing roles don't automatically receive them:

```dsl.openfga
model
  schema 1.1

type user

type role
  relations
    define assignee: [user]

type organization
  relations
    define admin: [user]
    define can_create_project: [role#assignee] or admin 
    define can_edit_project: [role#assignee] or admin 
    define can_delete_project: [role#assignee] or admin  # new permission
```

To grant the new permission to existing roles, create additional tuples:

```yaml
- user: role:acme-project-admin#assignee
  relation: can_delete_project
  object: organization:acme
```

You do not need to add these tuples when adding the new permission. End-users will add the new permission to their custom roles when they find it appropriate.

##### Pros and Cons

**✅ Advantages:**
- Supports user-defined roles
- Flexible permission assignment
- No model changes needed for new role instances

**❌ Disadvantages:**
- More complex than static relations
- Requires additional tuples for role-permission mapping

---

#### Approach 3: Role Assignments

The previous approach works well when custom roles are global for the organization. However, if you need roles that can be attached to different object instances with different members for each instance, you need role assignments.

##### Example: Project-Specific Admin Roles

Let's say you want a "Project Admin" role where each project can have different admins, but the role permissions remain consistent.

##### Step 1: Define the Role and its Permissions

Define a `role` type where you list all the permissions that any role can have:

```dsl.openfga
model
  schema 1.1

type role
  relations
    define can_view_project: [user:*]
    define can_edit_project: [user:*]
```

A "Project Admin" role can have `can_view_project` and `can_edit_project`:

```yaml
# Project Admin role has both the can_view_project and can_edit_project assigned
- user: user:*
  relation: can_view_project
  object: role:project-admin

- user: user:*
  relation: can_edit_project
  object: role:project-admin
```

##### Step 2: Assign Users to a Role on an Entity

Add a `role_assignment` type to assign users to the role:

```dsl.openfga
type role_assignment
  relations
    define assignee: [user]
    define role: [role]

    define can_view_project: assignee and can_view_project from role
    define can_edit_project: assignee and can_edit_project from role
```

##### Step 3: Connect to Your Objects

Define an `organization` type with an `admin` role. Then, define a `project` type that links to an `organization` and a `role_assignment`. Note that we are combining a static `admin` role with custom role assignments. We recommend to always use static roles when they are known in advance.

```dsl.openfga
type organization 
  relations
    define admin: [user]

type project
  relations
    define organization: [organization]
    define role_assignment: [role_assignment]
    
    # combine role assignments and static roles
    define can_edit_project: can_edit_project from role_assignment or admin from organization
    define can_view_project: can_view_project from role_assignment or admin from organization
```

##### Setting Up Role Assignments

1. **Create the role assignment instance**:

```yaml
- user: user:anne
  relation: assignee
  object: role_assignment:project-admin-openfga

- user: role:project-admin  
  relation: role
  object: role_assignment:project-admin-openfga
```

2. **Link the role assignment to the project**:

```yaml
- user: role_assignment:project-admin-openfga
  relation: role_assignment
  object: project:openfga
```
3. **Link the project to an organization**:

```yaml
- user: organization:acme
  relation: organization
  object: project:openfga
```
##### Pros and Cons

**✅ Advantages:**
- Maximum flexibility for instance-specific roles
- Reusable role definitions across different objects
- Fine-grained control over role membership

**❌ Disadvantages:**
- Most complex approach to implement
- Requires careful planning of the role hierarchy
- More tuples needed for setup and maintenance

---

#### Choosing the Right Approach

##### Decision Tree

1. **Do you need user-defined roles?**
   - No → Use **Relations as Roles**
   - Yes → Continue to step 2

2. **Do roles need different members per object instance?**
   - No → Use **Simple User-Defined Roles**
   - Yes → Use **Role Assignments**

##### Performance Considerations

- **Relations as Roles**: Fastest evaluation
- ***Simple User-Defined Roles**: Moderate performance impact
- **Role Assignments**: Highest performance impact

#### Best Practices

1. **Start simple**: Begin with relations as roles and evolve as needed
2. **Hybrid approach**: Combine static relations for well-known roles with dynamic roles for custom ones
3. **Documentation**: Clearly document your role model for your team
4. **Functional Testing**: [Write tests](https://github.com/openfga/openfga.dev/blob/main/../modeling/testing-models.mdx) to verify your model behaves as expected
5. **Performance Testing**: Test performance with realistic data volumes

#### Related Sections

<RelatedSection
  description="Check out these related resources for more information about adopting OpenFGA."
  relatedLinks={[
    {
      title: 'Custom Roles Step by Step',
      description: 'Follow a detailed walkthrough of implementing custom roles.',
      link: '../modeling/custom-roles',
    },
    {
      title: 'Multi-tenant RBAC Example',
      description: 'See a complete multi-tenant role-based access control implementation.',
      link: 'https://github.com/openfga/sample-stores/blob/main/stores/multitenant-rbac',
    },
    {
      title: 'Role Assignments Example',
      description: 'Explore a full role assignments implementation.',
      link: 'https://github.com/openfga/sample-stores/tree/main/stores/role-assignments',
    }
  ]}
/>

<!-- End of openfga/openfga.dev/docs/content/best-practices/modeling-roles.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/best-practices/modeling.mdx -->

---
title: Modeling Best Practices
slug: /best-practices/modeling
description: Best practices when creating FGA models
sidebar_position: 1
---
import {
  ProductName,
  ProductNameFormat,
  RelatedSection,
} from '@components/Docs';

### Modeling Best Practices

#### Dynamic vs Static types and relations

In <ProductName format={ProductNameFormat.ShortForm}/> the authorization policies are defined both in the model and tuples. You can decide how much weight you want to each one.

The model below can be used to implement authorization for any organization hierarchy, any resource hierarchy and any role hierarchy. 


```dsl.openfga
 model
    schema 1.1 

  type user

  type role
    relations
      define assignee: [user, role#assignee]

  type entity
    relations
     # Organizations can be hierarchical
      define parent : [entity]

      define editor : [role#assignee] or editor from parent
      define viewer : [role#assignee] or editor or viewer from parent
      
  type resource
    relations
      # Resources belong to an entity
      define entity : [entity]

      # Resources can have a parent
      define parent: [resource]
      
      define editor : [role#assignee] or editor from entity or editor from parent
      define viewer : [role#assignee] or editor or viewer from parent
```

We do not recommend this approach. Instead, you should create models that mimic as closely as possible the business logic of the application instead of generic models. The rule of thumb is that **if the end-user of the application can define certain entities/relations, then those should be represented in tuples. If not, it should be represented in the model**.

For example, when defining roles, in general you have certain roles that come built-in with the application like an “admin” or a “billing manager”. The way you would model that is very simple:

```dsl.openfga

  type user

  type organization
    relations
      define admin: [user]
      define billing_manager: [user]

      define can_manage_billing: admin or billing_manager
      define can_manage_users: admin
```

Defining the model in a way that closely resembles your application domain has several advantages:

  - **Enhanced clarity and maintainability**: Authorization logic is easier to understand, debug, and maintain. By just reading the model, developers or security auditors can readily grasp the meaning of each type and relationship.

  - **Better performance**: Models with more specific types and flatter hierarchies often lead to better performance. This is because <ProductName format={ProductNameFormat.ShortForm}/> can more efficiently process queries involving well-defined types and relationships compared to navigating complex, high-cardinality recursive relationships within a single generic type.

  - **Leveraging the model's flexibility**: <ProductName format={ProductNameFormat.ShortForm}/>'s modeling language is designed to be adaptable. You can define numerous distinct types and relationships without significant overhead. Model changes rarely require data migrations, allowing you to evolve your model as your application grows. <ProductName format={ProductNameFormat.ShortForm}/> is already a generic authorization engine, you don't need to build another one on top of it.

  - **Improved Collaboration**: The resource types that are owned by each application team can be maintained in independent [modules](https://github.com/openfga/openfga.dev/blob/main/./../modeling/modular-models.mdx). You can then control which application can write to specific resource types. For example, when using the API credential issued to a Document Management application, you can only write/delete tuples for documents and folders, but not for other types. This is not possible if you use a generic `resource` type, for example. 

##### Custom Roles

However, in some applications, you will want end-users to define their own roles. In that case, you do need a role type:

```dsl.openfga
 model
    schema 1.1 
 type user

  type role
   relations
     define assignee: [user]

  type organization
    relations
      define admin: [user]
      define billing_manager: [user]

      define can_manage_billing: [role#assignee] or admin or billing_manager
      define can_manage_users: [role#assignee] or admin
```

In this example we are combining static roles with dynamic roles. This is the recommended way of doing it. Do not always use generic roles. Define static roles for the ones you already know, and implement generic roles if your application needs them. Adding additional static roles is very easy, you just need to add a relation in the model, and it does not happen often. 

You can see a full example of implementing custom roles [here](https://github.com/openfga/sample-stores/tree/main/stores/custom-roles).

##### Organizational Hierarchies

If you are building a B2B SaaS application, you might encounter the following requirements:

- You need the employees of your organization to access customer data for help desk scenarios, or as a super-admin for disaster-recovery
- Your customers have a hierarchical organization structure that you'll need to represent. 

A possible way of modeling this would be with a recursive entity type:

```dsl.openfga
 model
    schema 1.1 

 type user

 type organization
    relations
      define parent: [organization]
      define admin: [user] or admin from parent
      define member: [user] or member from parent
```

You can use this construct to solve both problems. You define a root entity to represent your company, and then each entity can have a set of child entities that will inherit permissions. However, it always has a recursive relation that is more expensive to evaluate, and it does not fully represent the two different hierarchies you need.

If have the requirement of allowing the employees of your company to access the system, we recommend to use the model below.

```dsl.openfga
model 
  schema 1.1

 type user
 type system
    relations
       define admin: [user] 

 type organization
    relations
      define system: [system]
      define admin: [user] or admin from system
      define member: [user] 
```
This defines a hierarchy that is not recursive, which is faster to evaluate, and is easier to understand the model intent. You can see a full example of this scenario [here](https://github.com/openfga/sample-stores/blob/main/stores/superadmin).

If you also have the requirement of supporting hierarchical organizations, you can add that when you need it:

```dsl.openfga
model 
  schema 1.1

 type user
 type system
    relations
       define admin: [user] 

 type organization
    relations
      define system: [system]
      define parent: [organization]
      define admin: [user] or admin from system
      define member: [user] 
```
In this case, it's clear that you have two hierarchies, one is recursive and the other is not.

#### Related Sections

<RelatedSection
  description="Check out these related resources for more information about adopting OpenFGA"
  relatedLinks={[
    {
      title: 'Modular Authorization Models',
      description: 'Learn how to break down your authorization model into modules.',
      link: './../modeling/modular-models',
    }
  ]}
/>



<!-- End of openfga/openfga.dev/docs/content/best-practices/modeling.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/best-practices/overview.mdx -->

---
title: Best Practices
slug: /best-practices
description: Overview of best practices when adopting OpenFGA
sidebar_position: 1
---
import {
  ProductName,
  ProductNameFormat,
  RelatedSection,
} from '@components/Docs';

### <ProductName format={ProductNameFormat.ShortForm}/> Best Practices

This section contains a collection of best practices when implementing <ProductName format={ProductNameFormat.ShortForm}/>.

<RelatedSection
  description=""
  relatedLinks={[
    {
      title: 'Running OpenFGA in Production',
      description: 'Learn about best practices for running OpenFGA in production environments.',
      link: './best-practices/running-in-production',
    },
    {
      title: 'Adoption Patterns',
      description: 'Learn the best ways to introduce OpenFGA in your project.',
      link: './best-practices/adoption-patterns',
    },    
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/best-practices/overview.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/best-practices/running-in-production.mdx -->

---
title: Running OpenFGA in Production
description: Best Practices of Running OpenFGA in a Production Environment
slug: /best-practices/running-in-production
sidebar_position: 2
---

import {
  DocumentationNotice,
  RelatedSection,
} from "@components/Docs";

### Running OpenFGA in Production

<DocumentationNotice />

The following list outlines best practices for running OpenFGA in a production environment:

- [Configure Authentication](https://github.com/openfga/openfga.dev/blob/main/../getting-started/setup-openfga/configure-openfga.mdx#configuring-authentication)
- Enable HTTP TLS or gRPC TLS or both
- Set the log format to "json" and log level to "info"
- [Disable the Playground](https://github.com/openfga/openfga.dev/blob/main/../getting-started/setup-openfga/playground.mdx#disabling-the-playground)
- [Set Cluster](#cluster-recommendations)
- [Set Database Options](#database-recommendations)
- [Set Maximum Results](#maximum-results)
- [Set Concurrency Limits](#concurrency-limits)

#### Cluster recommendations

We recommend:

1. Turn on in-memory caching in Check API via flags. This will reduce latency of requests, but it will increase the staleness of OpenFGA's responses. Please see [Cache Expiration](https://github.com/openfga/openfga.dev/blob/main/../interacting/consistency.mdx#cache-expiration) for details on the flags.
2. Prefer having a small pool of servers with high capacity (memory and CPU cores) instead of a big pool of servers, to increase cache hit ratios and simplify pool management.
3. Turn on metrics collection via the flags `--metrics-enabled` and `--datastore-metrics-enabled`. This will allow you to debug issues.
4. Turn on tracing via the flag `--trace-enabled`, but set sampling ratio to a low value, for example `--trace-sample-ratio=0.3`. This will allow you to debug issues without overwhelming the tracing server. However, keep in mind that enabling tracing comes with a slight performance cost.

#### Database recommendations

To ensure good performance for OpenFGA, it is recommended that the [database](https://github.com/openfga/openfga.dev/blob/main/../getting-started/setup-openfga/configure-openfga.mdx#configuring-data-storage) be:
- Co-located in the same physical datacenter and network as your OpenFGA servers. This will minimize latency of database calls.
- Used exclusively for OpenFGA and not shared with other applications. This allows scaling the database independently and avoiding contention with your database.
- Bootstrapped and managed with the `openfga migrate` command. This will ensure the appropriate database indexes are created.

It's strongly recommended to fine-tune your server database connection settings to avoid having to re-establish database connections frequently. Establishing database connections is slow and will negatively impact performance, and so here are some guidelines for managing database connection settings:

- The server setting `OPENFGA_DATASTORE_MAX_OPEN_CONNS` should be set to be equal to your database's max connections. For example, in Postgres, you can see this value via running the SQL query `SHOW max_connections;`. If you are running multiple instances of the OpenFGA server, you should divide this setting equally among the instances. For example, if your database's `max_connections` is 100, and you have 2 OpenFGA instances, `OPENFGA_DATASTORE_MAX_OPEN_CONNS` should be set to 50 for each instance.

- The `OPENFGA_DATASTORE_MAX_IDLE_CONNS` should be set to a value no greater than the maximum open connections (see the bullet point above), but it should be set sufficiently high enough to avoid having to recreate connections on each request.

If, when monitoring your database stats, you see a lot of database connections being closed and subsequently reopened, then you should consider setting the `OPENFGA_DATASTORE_MAX_IDLE_CONNS` to the same number as `OPENFGA_DATASTORE_MAX_OPEN_CONNS`.

- If idle connections are getting reaped frequently, then consider increasing the `OPENFGA_DATASTORE_CONN_MAX_IDLE_TIME` to a large value. When in doubt, prioritize keeping connections around for longer rather than shorter, because doing so will drastically improve performance.

#### Concurrency limits
:::note
Before modifying concurrency limits please make sure you've followed the guidance for [Database Recommendations](#database-recommendations)
:::

OpenFGA queries such as Check, ListObjects and ListUsers can be quite database and CPU intensive in some cases. If you notice that a single request is consuming a lot of CPU or creating a high degree of database contention, then you may consider setting some concurrency limits to protect other requests from being negatively impacted by overly aggressive queries. 

The following table enumerates the server's concurrency specific settings:

| flag                                    | env                                           | config                           |
|-----------------------------------------|-----------------------------------------------|----------------------------------|
| --max-concurrent-reads-for-list-objects | OPENFGA_MAX_CONCURRENT_READS_FOR_LIST_OBJECTS | maxConcurrentReadsForListObjects |
| --max-concurrent-reads-for-list-users   | OPENFGA_MAX_CONCURRENT_READS_FOR_LIST_USERS   | maxConcurrentReadsForListUsers   |
| --max-concurrent-reads-for-check        | OPENFGA_MAX_CONCURRENT_READS_FOR_CHECK        | maxConcurrentReadsForCheck       |
| --resolve-node-limit                    | OPENFGA_RESOLVE_NODE_LIMIT                    | resolveNodeLimit                 |
| --resolve-node-breadth-limit            | OPENFGA_RESOLVE_NODE_BREADTH_LIMIT            | resolveNodeBreadthLimit          |
| --max-concurrent-checks-per-batch-check | OPENFGA_MAX_CONCURRENT_CHECKS_PER_BATCH_CHECK | maxConcurrentChecksPerBatchCheck |


Determining the right values for these settings will be based on a variety of factors including, but not limited to, the database specific deployment topology, the FGA model(s) involved, and the relationship tuples in the system. However, here are some high-level guidelines:

* If a single ListObjects or ListUsers query is negatively impacting other query endpoints by increasing their latency or their error rate, then consider setting a lower value for `OPENFGA_MAX_CONCURRENT_READS_FOR_LIST_OBJECTS` or `OPENFGA_MAX_CONCURRENT_READS_FOR_LIST_USERS`.

* If a single Check query is negatively impacting other query endpoints by increasing their latency or their error rate, then consider setting a lower value for `OPENFGA_MAX_CONCURRENT_READS_FOR_CHECK`.

If you still see high request latencies despite the guidance above, then you may additionally consider setting stricter limits on the query resolution behavior by limiting the resolution depth and resolution breadth. These can be controlled with the `OPENFGA_RESOLVE_NODE_LIMIT` and `OPENFGA_RESOLVE_NODE_BREADTH_LIMIT` settings, respectively. Consider these guidelines:

* `OPENFGA_RESOLVE_NODE_LIMIT` limits the resolution depth of a single query, and thus it sets an upper bound on how deep a relationship hierarchy may be. A high value will allow a single query to involve more hierarchical resolution and therefore more database queries, while a low value will reduce the number of hierarchical resolutions that will be allowed and thus reduce the number of database queries.

* `OPENFGA_RESOLVE_NODE_BREADTH_LIMIT` limits the resolution breadth. It sets an upper bound on the number of in-flight resolutions that can be taking place on one or more [usersets](https://github.com/openfga/openfga.dev/blob/main/../concepts.mdx#what-is-a-user). A high value will allow a single query to involve more concurrent evaluations to take place and therefore more database queries and server processes, while a low value will reduce the overall number of concurrent resolutions that will be allowed and thus reduce the number of database queries and server processes.

#### Maximum results

Both the ListObjects and ListUsers endpoints will continue retrieving results until one of the following conditions is met:

 - The maximum number of results is found
 - The entire pool of possible results has been searched
 - The API times out

By default, both ListObjects and ListUsers have a maximum results limit of 1,000. The higher the quantity of potential results in the system, the more time and resource-intensive it becomes to search for a large number of maximum results. This increased load can impact performance, potentially leading to time-outs in some cases. If your use case allows, consider setting a lower max results value via the `OPENFGA_LIST_OBJECTS_MAX_RESULTS` or `OPENFGA_LIST_USERS_MAX_RESULTS` configuration properties. This adjustment can lead to immediate improvements in time and resource efficiency.

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to run OpenFGA in production environment."
  relatedLinks={[
    {
      title: 'Data and API Best Practices',
      description: 'Learn the best practices for managing data and invoking APIs in production environment',
      link: '../getting-started/tuples-api-best-practices',
      id: '../getting-started/tuples-api-best-practices',
    },
 {
      title: 'Migrating Relations',
      description: 'Learn how to migrate relations in a production environment',
      link: '../modeling/migrating/migrating-relations',
      id: '../modeling/migrating/migrating-relations',
    }
  ]}
/>

<!-- End of openfga/openfga.dev/docs/content/best-practices/running-in-production.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/best-practices/source-of-truth.mdx -->

---
title: Source of Truth
sidebar_position: 3
slug: /best-practices/source-of-truth
description: Deciding where to store the "source of truth" for authorization data
---

import {
  ProductName,
  ProductNameFormat,
  RelatedSection,

} from '@components/Docs';

### When to use <ProductName format={ProductNameFormat.ShortForm}/> as the 'source of truth' for authorization data

<ProductName format={ProductNameFormat.ShortForm}/> is inspired by [Google’s Zanzibar](https://research.google/pubs/zanzibar-googles-consistent-global-authorization-system/). In Google’s architecture, Zanzibar is an extremely efficient system for authorization checks, but it's never the source of truth for application data. The [Read endpoint](https://openfga.dev/docs/interacting/relationship-queries#read) is mostly used when you need to inspect the stored data, e.g. for troubleshooting consistency issues.

For developers using <ProductName format={ProductNameFormat.ShortForm}/>, following Google's approach isn't always practical. In most cases, applications will use <ProductName format={ProductNameFormat.ShortForm}/> as the source of truth for some data.

**When <ProductName format={ProductNameFormat.ShortForm}/>  is not the right source of truth:**

- User data: The source of truth for user profile data is typically an identity provider like Azure, Okta or Auth0. 
- Entity hierarchies: Structures like project/tickets or folder/documents already live in application's databases. Repeatedly querying <ProductName format={ProductNameFormat.ShortForm}/> just to navigate that hierarchy would be inefficient. Having this in the application's database would allow for better optimizations when searching within a folder (see: [search with permissions](https://github.com/openfga/openfga.dev/blob/main/../interacting/search-with-permissions.mdx)), as it would let the applications narrow down the scope of what it needs to check, and then call check in parallel instead of filtering through other methods.
- Data relevant for search and filtering: When performing searches, you need to combine data that's on your database and data that's in <ProductName format={ProductNameFormat.ShortForm}/>. Your application's database is the right place to do filtering/sorting/joins. The data required for performing those operations should live in application's databases.

**When OpenFGA is a good source of truth:**

- Fine-grained permissions: If an application allows users to assign permissions directly to resources (e.g., sharing a document), and you don't need to store that data in the application's database, you can store it only in <ProductName format={ProductNameFormat.ShortForm}/>.

- Role membership: If you are not using another system to manage roles, storing role membership in <ProductName format={ProductNameFormat.ShortForm}/> is reasonable. Remember that <ProductName format={ProductNameFormat.ShortForm}/> does not store role metadata (like names or descriptions), so you'll still need a 'Roles' table in your application's database.


<!-- End of openfga/openfga.dev/docs/content/best-practices/source-of-truth.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/setup-openfga/access-control.mdx -->

---
title: Setup Access Control
description: How to enable and setup the built-in access control OpenFGA server (experimental)
sidebar_position: 2
slug: /getting-started/setup-openfga/access-control
---

import {
  DocumentationNotice,
  RelatedSection,
} from "@components/Docs";

### 🛡️Setup Access Control

In OpenFGA [v1.7.0](https://github.com/openfga/openfga/releases/tag/v1.7.0), we introduced an experimental built-in access control feature that allows you to control access to your OpenFGA server. It relies on a control store with its own model and tuples to authorize requests to the OpenFGA server itself.

Currently, there is no provided way to initialize that access control store and model, nor is there a way to bootstrap the client IDs that are supposed to be admins.

:::caution Warning
The built-in access control feature in OpenFGA is experimental and is not recommended for production use. We are looking for feedback on this, so if you do try it, please reach out on our [openfga Slack channel](https://github.com/openfga/openfga.dev/blob/main/../../community.mdx) in the CNCF community.
:::

Read the following steps to enable access control.

#### Requirements

- OIDC Provider: You need to have an OIDC provider [set up and configured](https://github.com/openfga/openfga.dev/blob/main/./configure-openfga.mdx#oidc) in your OpenFGA server set up to use access control.
- A Client ID ready to be used: You need to have the initial (admin) client ID that you want to manage access to your OpenFGA server.
- The FGA CLI: While the CLI is not strictly required, you need to follow the steps below. You can install it by following the instructions [here](https://github.com/openfga/openfga.dev/blob/main/../cli.mdx). If you do not want to use the CLI, you can call the API with the equivalent SDK or REST calls.

#### 01. Ensure the server is running (with access control disabled)

This is important. If you enable access control before setting up the store and model and grant your initial client ID access, you will lock yourself out of the server, and you will have to turn it back off.

#### 02. Create the access control store and model

We will be using the following model to enable access control.

:::note Customizing your access control model
You may choose to modify this model to suit your needs, however, keep in mind that configuring the model may not be supported in the future and you may be responsible for your own migrations at that point.

The required types and relations that need to be defined are marked in the model below.
:::

```dsl.openfga
model
  schema 1.1

type system # required
  relations
    define admin: [application] # required
    define can_call_create_stores: admin # required
    define can_call_list_stores: [application, application:*] or admin # required

type application # required

type store # required
  relations
    define system: [system] # required
    define admin: [application] or admin from system # required
    define model_writer: [application] or admin
    define reader: [application] or admin
    define writer: [application] or admin
    define can_call_delete_store: admin # required
    define can_call_get_store: reader or writer or model_writer # required
    define can_call_check: reader # required
    define can_call_expand: reader # required
    define can_call_list_objects: reader # required
    define can_call_list_users: reader # required
    define can_call_read: reader # required
    define can_call_read_assertions: reader or model_writer # required
    define can_call_read_authorization_models: reader or model_writer # required
    define can_call_read_changes: reader # required
    define can_call_write: writer # required
    define can_call_write_assertions: model_writer # required
    define can_call_write_authorization_models: model_writer # required

type module # required
  relations
    define store: [store] # required
    define writer: [application]
    define can_call_write: writer or writer from store # required
```

1. Place the model above in a file called `model.fga`.
2. Run the following command to create the store and model:
   ```shell
   fga store create --name root-access-control --model ./model.fga
   ```
   This prints a store ID and model ID. You will need these IDs in the following steps.
3. Grant your initial client ID access. You can do so by writing a tuple to the access control store you just created. The tuple should be of the type `application` and should have the `client_id` field set to the client ID of the client you want to grant access to. You can use the FGA CLI to do this:
   ```shell
    fga tuple write --store-id "${ACCESS_CONTROL_STORE_ID}" "application:${FGA_ADMIN_CLIENT_ID}" admin "system:fga"
   ```
    Replace `${ACCESS_CONTROL_STORE_ID}` with the store ID you received in the previous step; replace `${FGA_ADMIN_CLIENT_ID}` with the client ID you want to grant access to.

#### 03. Enable access control

##### i. Enable access control in the server
1. Enable the experimental support for access control by setting the environment variable `OPENFGA_EXPERIMENTALS` to `enable-access-control`.
2. Enable the access control feature by setting the environment variable `OPENFGA_ACCESS_CONTROL_ENABLED` to `true`.
3. Set the environment variable `OPENFGA_ACCESS_CONTROL_STORE_ID` to the store ID you received in the previous step.
4. Set the environment variable `OPENFGA_ACCESS_CONTROL_MODEL_ID` to the model ID you received in the previous step.

##### ii. Customize what claim you want the API to use (optional)

By default, the API will use the following claims (in order) in the OIDC token to identify the client. If you want to use a different claim, you can set the environment variable `OPENFGA_AUTHN_OIDC_CLIENT_ID_CLAIMS` to the claim(s) you want to use.

<!-- markdown-link-check-disable -->
If the claims are not set in the configuration, the following claims are used as default (in order):
1. `azp`: following [the OpenID standard](https://openid.net/specs/openid-connect-core-1_0.html#IDToken)
2. `client_id` following [RFC9068](https://www.rfc-editor.org/rfc/rfc9068.html#name-data-structure)
<!-- markdown-link-check-enable -->

That means that if the `azp` claim is present in the token, it will be used to identify the client. If not, the `client_id` claim will be used instead.

For example, you can set the environment variable `OPENFGA_AUTHN_OIDC_CLIENT_ID_CLAIMS` to `user_id,employee_id,client_id` to allow the OpenFGA server to authorize based on:
1. Use the `user_id` claim if present in the token.
2. If not try to use the `employee_id` claim if present.
3. If not try to use the `client_id` claim.

#### iii. Restart the server
You now need to restart the OpenFGA server in order for the configuration changes above to take effect. Congrats, you now have access control enabled! 🎉🎉

#### 04. Grant access to a store

You can now use the admin client ID to manage access to your OpenFGA server. We will call it `FGA_ADMIN_CLIENT_ID` in the following examples to differentiate it from the client ID (called `FGA_CLIENT_ID`) you are granting access to.

We will also use `ACCESS_CONTROL_STORE_ID` as the store ID of the access control store, and `STORE_ID` as the store ID you are granting the client access to.

1. Grant access to a store (based on the model above, your choices are `admin`, `model_writer`, `writer` and `reader`).
   ```shell
   fga tuple write --store-id "${ACCESS_CONTROL_STORE_ID}" "application:${FGA_CLIENT_ID}" model_writer "store:${STORE_ID}" --client-id "${FGA_ADMIN_CLIENT_ID}" --client-secret ... --api-token-issuer ... --api-audience ...
   ```

2. Grant access to writing tuples of a certain module in a store.

   In order to grant access to only write to relations in certain modules, you must have a model with modules. Refer to the [modular models documentation](https://github.com/openfga/openfga.dev/blob/main/../../modeling/modular-models.mdx) for more on that feature.

   If you want to grant access to a module in a store, you must namespace the module ID with the store ID, so the object of the tuple will be of the form `module:<store-id>|<module-name>`.
   ```shell
   fga tuple write --store-id "${ACCESS_CONTROL_STORE_ID}" "application:${FGA_CLIENT_ID}" writer "module:${STORE_ID}|<module-name>" --client-id "${FGA_ADMIN_CLIENT_ID}" --client-secret ... --api-token-issuer ... --api-audience ...
   ```

:::note Note
If you are calling `Write` with a credential that only has access to certain modules and not the store, you will not be able to send tuples for more than 1 module in a certain request or you will get the following error: `the principal cannot write tuples of more than 1 module(s) in a single request`
:::

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to use OpenFGA."
  relatedLinks={[
    {
      title: 'Setup OpenFGA',
      description: 'Learn how to setup and configure an OpenFGA server',
      link: './configure-openfga',
      id: './configure-openfga',
    },
    {
      title: 'Setup OIDC',
      description: 'Learn how to setup and configure an OpenFGA server',
      link: './configure-openfga#oidc',
      id: './configure-openfga#oidc',
    },
    {
      title: 'Running OpenFGA in Production',
      description: 'Learn the best practices of running OpenFGA in a production environment',
      link: '../../best-practices/running-in-production',
      id: './best-practices/running-in-production',
    }
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/getting-started/setup-openfga/access-control.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/setup-openfga/configuration.mdx -->

---
title: OpenFGA Configuration Options
description: Configuring Options for the OpenFGA Server
sidebar_position: 1
slug: /getting-started/setup-openfga/configuration
---
import {
  RelatedSection,
} from "@components/Docs";
import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

### OpenFGA Configuration Options

#### Passing in the options

You can configure the OpenFGA server in three ways:

- Using a configuration file.
- Using environment variables.
- Using command line parameters.

If the same option is configured in multiple ways the command line parameters will take precedence over environment variables, which will take precedence over the configuration file.

<Tabs groupId={"configuration"}>
<TabItem value={"configuration file"} label={"Configuration File"}>

You can configure the OpenFGA server with a `config.yaml` file, which can be specified in either:
- `/etc/openfga`
- `$HOME/.openfga`
- `.` (i.e., the current working directory).

The OpenFGA server will search for the configuration file in the above order.

Here is a sample configuration to run OpenFGA with a Postgres database and using a preshared key for authentication:

```yaml
datastore:
  engine: postgres
  uri: postgres://user:password@localhost:5432/mydatabase
authn:
  method: preshared
  preshared:
    keys: ["key1", "key2"]
playground:
  enabled: false
```

</TabItem>

<TabItem value={"environment variables"} label={"Environment Variables"}>

The OpenFGA server supports **environment variables** for configuration, and they will take priority over your configuration file.
Each variable must be prefixed with `OPENFGA_` and followed by your option in uppercase (`datastore.engine` becomes `OPENFGA_DATASTORE_ENGINE`), e.g.

```shell
# Running as a binary
export OPENFGA_DATASTORE_ENGINE=postgres
export OPENFGA_DATASTORE_URI='postgres://postgres:password@postgres:5432/postgres?sslmode=disable'
export OPENFGA_AUTHN_METHOD=preshared
export OPENFGA_AUTHN_PRESHARED_KEYS='key1,key2'
export OPENFGA_PLAYGROUND_ENABLED=false
openfga run

# Running in docker
docker run docker.io/openfga/openfga:latest \ 
  -e OPENFGA_DATASTORE_ENGINE=postgres \ 
  -e OPENFGA_DATASTORE_URI='postgres://postgres:password@postgres:5432/postgres?sslmode=disable' \ 
  -e OPENFGA_AUTHN_METHOD=preshared \ 
  -e OPENFGA_AUTHN_PRESHARED_KEYS='key1,key2' \ 
  -e OPENFGA_PLAYGROUND_ENABLED=false \ 
  run
```

</TabItem>

<TabItem value={"command line parameters"} label={"Command Line Parameters (Flags)"}>

Command line parameters take precedence over environment variables and options in the configuration file. They are prefixed with `--` (`OPENFGA_DATASTORE_ENGINE` becomes `--datastore-engine`), e.g.

```shell
# Running as a binary
openfga run \ 
  --datastore-engine postgres \ 
  --datastore-uri 'postgres://postgres:password@postgres:5432/postgres?sslmode=disable' \ 
  --authn-method=preshared \ 
  --authn-preshared-keys='key1,key2' \ 
  --playground-enabled=false

# Running in docker
docker run docker.io/openfga/openfga:latest run \ 
  --datastore-engine postgres \ 
  --datastore-uri 'postgres://postgres:password@postgres:5432/postgres?sslmode=disable' \ 
  --authn-method=preshared \ 
  --authn-preshared-keys='key1,key2' \ 
  --playground-enabled=false
```

</TabItem>
</Tabs>

#### List of options

The following table lists the configuration options for the OpenFGA server [v1.8.9](https://github.com/openfga/openfga/releases/tag/v1.8.9), based on the [config-schema.json](https://raw.githubusercontent.com/openfga/openfga/refs/tags/v1.8.9/.config-schema.json).

| Config File | Env Var | Flag Name | Type | Description | Default Value |
|-------------|---------|-----------|------|-------------|---------------|
| `maxTuplesPerWrite` | <div id="OPENFGA_MAX_TUPLES_PER_WRITE"><code>OPENFGA_MAX_TUPLES_PER_WRITE</code></div> | `max-tuples-per-write` | integer | The maximum allowed number of tuples per Write transaction. | `100` |
| `maxTypesPerAuthorizationModel` | <div id="OPENFGA_MAX_TYPES_PER_AUTHORIZATION_MODEL"><code>OPENFGA_MAX_TYPES_PER_AUTHORIZATION_MODEL</code></div> | `max-types-per-authorization-model` | integer | The maximum allowed number of type definitions per authorization model. | `100` |
| `maxAuthorizationModelSizeInBytes` | <div id="OPENFGA_MAX_AUTHORIZATION_MODEL_SIZE_IN_BYTES"><code>OPENFGA_MAX_AUTHORIZATION_MODEL_SIZE_IN_BYTES</code></div> | `max-authorization-model-size-in-bytes` | integer | The maximum size in bytes allowed for persisting an Authorization Model (default is 256KB). | `262144` |
| `maxConcurrentReadsForCheck` | <div id="OPENFGA_MAX_CONCURRENT_READS_FOR_CHECK"><code>OPENFGA_MAX_CONCURRENT_READS_FOR_CHECK</code></div> | `max-concurrent-reads-for-check` | integer | The maximum allowed number of concurrent reads in a single Check query (default is MaxUint32). | `4294967295` |
| `maxConcurrentReadsForListObjects` | <div id="OPENFGA_MAX_CONCURRENT_READS_FOR_LIST_OBJECTS"><code>OPENFGA_MAX_CONCURRENT_READS_FOR_LIST_OBJECTS</code></div> | `max-concurrent-reads-for-list-objects` | integer | The maximum allowed number of concurrent reads in a single ListObjects query (default is MaxUint32). | `4294967295` |
| `maxConcurrentReadsForListUsers` | <div id="OPENFGA_MAX_CONCURRENT_READS_FOR_LIST_USERS"><code>OPENFGA_MAX_CONCURRENT_READS_FOR_LIST_USERS</code></div> | `max-concurrent-reads-for-list-users` | integer | The maximum allowed number of concurrent reads in a single ListUsers query (default is MaxUint32). | `4294967295` |
| `maxConcurrentChecksPerBatchCheck` | <div id="OPENFGA_MAX_CONCURRENT_CHECKS_PER_BATCH_CHECK"><code>OPENFGA_MAX_CONCURRENT_CHECKS_PER_BATCH_CHECK</code></div> | `max-concurrent-checks-per-batch-check` | integer | The maximum number of checks that can be processed concurrently in a batch check request. | `50` |
| `maxChecksPerBatchCheck` | <div id="OPENFGA_MAX_CHECKS_PER_BATCH_CHECK"><code>OPENFGA_MAX_CHECKS_PER_BATCH_CHECK</code></div> | `max-checks-per-batch-check` | integer | The maximum number of tuples allowed in a BatchCheck request. | `50` |
| `maxConditionEvaluationCost` | <div id="OPENFGA_MAX_CONDITION_EVALUATION_COST"><code>OPENFGA_MAX_CONDITION_EVALUATION_COST</code></div> | `max-condition-evaluation-cost` | integer | The maximum cost for CEL condition evaluation before a request returns an error (default is 100). | `100` |
| `changelogHorizonOffset` | <div id="OPENFGA_CHANGELOG_HORIZON_OFFSET"><code>OPENFGA_CHANGELOG_HORIZON_OFFSET</code></div> | `changelog-horizon-offset` | integer | The offset (in minutes) from the current time. Changes that occur after this offset will not be included in the response of ReadChanges. |  |
| `resolveNodeLimit` | <div id="OPENFGA_RESOLVE_NODE_LIMIT"><code>OPENFGA_RESOLVE_NODE_LIMIT</code></div> | `resolve-node-limit` | integer | Maximum resolution depth to attempt before throwing an error (defines how deeply nested an authorization model can be before a query errors out). | `25` |
| `resolveNodeBreadthLimit` | <div id="OPENFGA_RESOLVE_NODE_BREADTH_LIMIT"><code>OPENFGA_RESOLVE_NODE_BREADTH_LIMIT</code></div> | `resolve-node-breadth-limit` | integer | Defines how many nodes on a given level can be evaluated concurrently in a Check resolution tree. | `100` |
| `listObjectsDeadline` | <div id="OPENFGA_LIST_OBJECTS_DEADLINE"><code>OPENFGA_LIST_OBJECTS_DEADLINE</code></div> | `list-objects-deadline` | string (duration) | The timeout deadline for serving ListObjects requests | `3s` |
| `listObjectsMaxResults` | <div id="OPENFGA_LIST_OBJECTS_MAX_RESULTS"><code>OPENFGA_LIST_OBJECTS_MAX_RESULTS</code></div> | `list-objects-max-results` | integer | The maximum results to return in the non-streaming ListObjects API response. If 0, all results can be returned | `1000` |
| `listUsersDeadline` | <div id="OPENFGA_LIST_USERS_DEADLINE"><code>OPENFGA_LIST_USERS_DEADLINE</code></div> | `list-users-deadline` | string (duration) | The timeout deadline for serving ListUsers requests. If 0s, there is no deadline | `3s` |
| `listUsersMaxResults` | <div id="OPENFGA_LIST_USERS_MAX_RESULTS"><code>OPENFGA_LIST_USERS_MAX_RESULTS</code></div> | `list-users-max-results` | integer | The maximum results to return in ListUsers API response. If 0, all results can be returned | `1000` |
| `requestDurationDatastoreQueryCountBuckets` | <div id="OPENFGA_REQUEST_DURATION_DATASTORE_QUERY_COUNT_BUCKETS"><code>OPENFGA_REQUEST_DURATION_DATASTORE_QUERY_COUNT_BUCKETS</code></div> | `request-duration-datastore-query-count-buckets` | []integer | Datastore query count buckets used to label the histogram metric for measuring request duration. | `50,200` |
| `requestDurationDispatchCountBuckets` | <div id="OPENFGA_REQUEST_DURATION_DISPATCH_COUNT_BUCKETS"><code>OPENFGA_REQUEST_DURATION_DISPATCH_COUNT_BUCKETS</code></div> | `request-duration-dispatch-count-buckets` | []integer | Dispatch count buckets used to label the histogram metric for measuring request duration. | `50,200` |
| `contextPropagationToDatastore` | <div id="OPENFGA_CONTEXT_PROPAGATION_TO_DATASTORE"><code>OPENFGA_CONTEXT_PROPAGATION_TO_DATASTORE</code></div> | `context-propagation-to-datastore` | boolean | Propagate a requests context to the datastore implementation. Settings this parameter can result in connection pool draining on request aborts and timeouts. | `false` |
| `experimentals` | <div id="OPENFGA_EXPERIMENTALS"><code>OPENFGA_EXPERIMENTALS</code></div> | `experimentals` | []string (enum=[`enable-check-optimizations`, `enable-list-objects-optimizations`, `enable-access-control`]) | a list of experimental features to enable | `` |
| `accessControl.enabled` | <div id="OPENFGA_ACCESS_CONTROL_ENABLED"><code>OPENFGA_ACCESS_CONTROL_ENABLED</code></div> | `access-control-enabled` | boolean | Enable/disable the access control store. | `false` |
| `accessControl.storeId` | <div id="OPENFGA_ACCESS_CONTROL_STORE_ID"><code>OPENFGA_ACCESS_CONTROL_STORE_ID</code></div> | `access-control-store-id` | string | The storeId to be used for the access control store. |  |
| `accessControl.modelId` | <div id="OPENFGA_ACCESS_CONTROL_MODEL_ID"><code>OPENFGA_ACCESS_CONTROL_MODEL_ID</code></div> | `access-control-model-id` | string | The modelId to be used for the access control store. |  |
| `playground.enabled` | <div id="OPENFGA_PLAYGROUND_ENABLED"><code>OPENFGA_PLAYGROUND_ENABLED</code></div> | `playground-enabled` | boolean | Enable/disable the OpenFGA Playground. | `true` |
| `playground.port` | <div id="OPENFGA_PLAYGROUND_PORT"><code>OPENFGA_PLAYGROUND_PORT</code></div> | `playground-port` | integer | The port to serve the local OpenFGA Playground on. | `3000` |
| `profiler.enabled` | <div id="OPENFGA_PROFILER_ENABLED"><code>OPENFGA_PROFILER_ENABLED</code></div> | `profiler-enabled` | boolean | Enabled/disable pprof profiling. | `false` |
| `profiler.addr` | <div id="OPENFGA_PROFILER_ADDR"><code>OPENFGA_PROFILER_ADDR</code></div> | `profiler-addr` | string | The host:port address to serve the pprof profiler server on. | `:3001` |
| `datastore.engine` | <div id="OPENFGA_DATASTORE_ENGINE"><code>OPENFGA_DATASTORE_ENGINE</code></div> | `datastore-engine` | string (enum=[`memory`, `postgres`, `mysql`, `sqlite`]) | The datastore engine that will be used for persistence. | `memory` |
| `datastore.uri` | <div id="OPENFGA_DATASTORE_URI"><code>OPENFGA_DATASTORE_URI</code></div> | `datastore-uri` | string | The connection uri to use to connect to the datastore (for any engine other than 'memory'). |  |
| `datastore.username` | <div id="OPENFGA_DATASTORE_USERNAME"><code>OPENFGA_DATASTORE_USERNAME</code></div> | `datastore-username` | string | The connection username to connect to the datastore (overwrites any username provided in the connection uri). |  |
| `datastore.password` | <div id="OPENFGA_DATASTORE_PASSWORD"><code>OPENFGA_DATASTORE_PASSWORD</code></div> | `datastore-password` | string | The connection password to connect to the datastore (overwrites any password provided in the connection uri). |  |
| `datastore.maxCacheSize` | <div id="OPENFGA_DATASTORE_MAX_CACHE_SIZE"><code>OPENFGA_DATASTORE_MAX_CACHE_SIZE</code></div> | `datastore-max-cache-size` | integer | The maximum number of authorization models that will be cached in memory | `100000` |
| `datastore.maxOpenConns` | <div id="OPENFGA_DATASTORE_MAX_OPEN_CONNS"><code>OPENFGA_DATASTORE_MAX_OPEN_CONNS</code></div> | `datastore-max-open-conns` | integer | The maximum number of open connections to the datastore. | `30` |
| `datastore.maxIdleConns` | <div id="OPENFGA_DATASTORE_MAX_IDLE_CONNS"><code>OPENFGA_DATASTORE_MAX_IDLE_CONNS</code></div> | `datastore-max-idle-conns` | integer | the maximum number of connections to the datastore in the idle connection pool. | `10` |
| `datastore.connMaxIdleTime` | <div id="OPENFGA_DATASTORE_CONN_MAX_IDLE_TIME"><code>OPENFGA_DATASTORE_CONN_MAX_IDLE_TIME</code></div> | `datastore-conn-max-idle-time` | string (duration) | the maximum amount of time a connection to the datastore may be idle | `0s` |
| `datastore.connMaxLifetime` | <div id="OPENFGA_DATASTORE_CONN_MAX_LIFETIME"><code>OPENFGA_DATASTORE_CONN_MAX_LIFETIME</code></div> | `datastore-conn-max-lifetime` | string (duration) | the maximum amount of time a connection to the datastore may be reused | `0s` |
| `datastore.metrics.enabled` | <div id="OPENFGA_DATASTORE_METRICS_ENABLED"><code>OPENFGA_DATASTORE_METRICS_ENABLED</code></div> | `datastore-metrics-enabled` | boolean | enable/disable sql metrics for the datastore | `false` |
| `authn.method` | <div id="OPENFGA_AUTHN_METHOD"><code>OPENFGA_AUTHN_METHOD</code></div> | `authn-method` | string (enum=[`none`, `preshared`, `oidc`]) | The authentication method to use. | `none` |
| `authn.preshared.keys` | <div id="OPENFGA_AUTHN_PRESHARED_KEYS"><code>OPENFGA_AUTHN_PRESHARED_KEYS</code></div> | `authn-preshared-keys` | []string | List of preshared keys used for authentication |  |
| `authn.oidc.issuer` | <div id="OPENFGA_AUTHN_OIDC_ISSUER"><code>OPENFGA_AUTHN_OIDC_ISSUER</code></div> | `authn-oidc-issuer` | string | The OIDC issuer (authorization server) signing the tokens. |  |
| `authn.oidc.audience` | <div id="OPENFGA_AUTHN_OIDC_AUDIENCE"><code>OPENFGA_AUTHN_OIDC_AUDIENCE</code></div> | `authn-oidc-audience` | string | The OIDC audience of the tokens being signed by the authorization server. |  |
| `authn.oidc.issuerAliases` | <div id="OPENFGA_AUTHN_OIDC_ISSUER_ALIASES"><code>OPENFGA_AUTHN_OIDC_ISSUER_ALIASES</code></div> | `authn-oidc-issuer-aliases` | []string | the OIDC issuer DNS aliases that will be accepted as valid when verifying the `iss` field of the JWTs. |  |
| `authn.oidc.subjects` | <div id="OPENFGA_AUTHN_OIDC_SUBJECTS"><code>OPENFGA_AUTHN_OIDC_SUBJECTS</code></div> | `authn-oidc-subjects` | []string | the OIDC subject names that will be accepted as valid when verifying the `sub` field of the JWTs. If empty, every `sub` will be allowed |  |
| `authn.oidc.clientIdClaims` | <div id="OPENFGA_AUTHN_OIDC_CLIENT_ID_CLAIMS"><code>OPENFGA_AUTHN_OIDC_CLIENT_ID_CLAIMS</code></div> | `authn-oidc-client-id-claims` | []string | the OIDC client id claims that will be used to parse the clientID - configure in order of priority (first is highest). Defaults to [`azp`, `client_id`] |  |
| `grpc.addr` | <div id="OPENFGA_GRPC_ADDR"><code>OPENFGA_GRPC_ADDR</code></div> | `grpc-addr` | string | The host:port address to serve the grpc server on. | `0.0.0.0:8081` |
| `grpc.tls.enabled` | <div id="OPENFGA_GRPC_TLS_ENABLED"><code>OPENFGA_GRPC_TLS_ENABLED</code></div> | `grpc-tls-enabled` | boolean | Enables or disables transport layer security (TLS). | `false` |
| `grpc.tls.cert` | <div id="OPENFGA_GRPC_TLS_CERT"><code>OPENFGA_GRPC_TLS_CERT</code></div> | `grpc-tls-cert` | string | The (absolute) file path of the certificate to use for the TLS connection. |  |
| `grpc.tls.key` | <div id="OPENFGA_GRPC_TLS_KEY"><code>OPENFGA_GRPC_TLS_KEY</code></div> | `grpc-tls-key` | string | The (absolute) file path of the TLS key that should be used for the TLS connection. |  |
| `http.enabled` | <div id="OPENFGA_HTTP_ENABLED"><code>OPENFGA_HTTP_ENABLED</code></div> | `http-enabled` | boolean | Enables or disables the OpenFGA HTTP server. If this is set to true then 'grpc.enabled' must be set to true. | `true` |
| `http.addr` | <div id="OPENFGA_HTTP_ADDR"><code>OPENFGA_HTTP_ADDR</code></div> | `http-addr` | string | The host:port address to serve the HTTP server on. | `0.0.0.0:8080` |
| `http.tls.enabled` | <div id="OPENFGA_HTTP_TLS_ENABLED"><code>OPENFGA_HTTP_TLS_ENABLED</code></div> | `http-tls-enabled` | boolean | Enables or disables transport layer security (TLS). | `false` |
| `http.tls.cert` | <div id="OPENFGA_HTTP_TLS_CERT"><code>OPENFGA_HTTP_TLS_CERT</code></div> | `http-tls-cert` | string | The (absolute) file path of the certificate to use for the TLS connection. |  |
| `http.tls.key` | <div id="OPENFGA_HTTP_TLS_KEY"><code>OPENFGA_HTTP_TLS_KEY</code></div> | `http-tls-key` |  | The (absolute) file path of the TLS key that should be used for the TLS connection. |  |
| `http.upstreamTimeout` | <div id="OPENFGA_HTTP_UPSTREAM_TIMEOUT"><code>OPENFGA_HTTP_UPSTREAM_TIMEOUT</code></div> | `http-upstream-timeout` | string | The timeout duration for proxying HTTP requests upstream to the grpc endpoint. | `3s` |
| `http.corsAllowedOrigins` | <div id="OPENFGA_HTTP_CORS_ALLOWED_ORIGINS"><code>OPENFGA_HTTP_CORS_ALLOWED_ORIGINS</code></div> | `http-cors-allowed-origins` | []string | List of allowed origins for CORS requests | `*` |
| `http.corsAllowedHeaders` | <div id="OPENFGA_HTTP_CORS_ALLOWED_HEADERS"><code>OPENFGA_HTTP_CORS_ALLOWED_HEADERS</code></div> | `http-cors-allowed-headers` | []string | List of allowed headers for CORS requests | `*` |
| `log.format` | <div id="OPENFGA_LOG_FORMAT"><code>OPENFGA_LOG_FORMAT</code></div> | `log-format` | string (enum=[`text`, `json`]) | The log format to output logs in. For production we recommend 'json' format. | `text` |
| `log.level` | <div id="OPENFGA_LOG_LEVEL"><code>OPENFGA_LOG_LEVEL</code></div> | `log-level` | string (enum=[`none`, `debug`, `info`, `warn`, `error`, `panic`, `fatal`]) | The log level to set. For production we recommend 'info' format. | `info` |
| `log.timestampFormat` | <div id="OPENFGA_LOG_TIMESTAMP_FORMAT"><code>OPENFGA_LOG_TIMESTAMP_FORMAT</code></div> | `log-timestamp-format` | string (enum=[`Unix`, `ISO8601`]) | The timestamp format to use for the log output. | `Unix` |
| `trace.enabled` | <div id="OPENFGA_TRACE_ENABLED"><code>OPENFGA_TRACE_ENABLED</code></div> | `trace-enabled` | boolean | Enable tracing. | `false` |
| `trace.otlp.endpoint` | <div id="OPENFGA_TRACE_OTLP_ENDPOINT"><code>OPENFGA_TRACE_OTLP_ENDPOINT</code></div> | `trace-otlp-endpoint` | string | The grpc endpoint of the trace collector | `0.0.0.0:4317` |
| `trace.otlp.tls.enabled` | <div id="OPENFGA_TRACE_OTLP_TLS_ENABLED"><code>OPENFGA_TRACE_OTLP_TLS_ENABLED</code></div> | `trace-otlp-tls-enabled` | boolean | Whether to use TLS connection for the trace collector | `false` |
| `trace.sampleRatio` | <div id="OPENFGA_TRACE_SAMPLE_RATIO"><code>OPENFGA_TRACE_SAMPLE_RATIO</code></div> | `trace-sample-ratio` | number | The fraction of traces to sample. 1 means all, 0 means none. | `0.2` |
| `trace.serviceName` | <div id="OPENFGA_TRACE_SERVICE_NAME"><code>OPENFGA_TRACE_SERVICE_NAME</code></div> | `trace-service-name` | string | The service name included in sampled traces. | `openfga` |
| `metrics.enabled` | <div id="OPENFGA_METRICS_ENABLED"><code>OPENFGA_METRICS_ENABLED</code></div> | `metrics-enabled` | boolean | enable/disable prometheus metrics on the '/metrics' endpoint | `true` |
| `metrics.addr` | <div id="OPENFGA_METRICS_ADDR"><code>OPENFGA_METRICS_ADDR</code></div> | `metrics-addr` | string | the host:port address to serve the prometheus metrics server on | `0.0.0.0:2112` |
| `metrics.enableRPCHistograms` | <div id="OPENFGA_METRICS_ENABLE_RPC_HISTOGRAMS"><code>OPENFGA_METRICS_ENABLE_RPC_HISTOGRAMS</code></div> | `metrics-enable-rpc-histograms` | boolean | enables prometheus histogram metrics for RPC latency distributions | `false` |
| `checkCache.limit` | <div id="OPENFGA_CHECK_CACHE_LIMIT"><code>OPENFGA_CHECK_CACHE_LIMIT</code></div> | `check-cache-limit` | integer | the size limit (in items) of the cache for Check (queries and iterators) | `10000` |
| `checkIteratorCache.enabled` | <div id="OPENFGA_CHECK_ITERATOR_CACHE_ENABLED"><code>OPENFGA_CHECK_ITERATOR_CACHE_ENABLED</code></div> | `check-iterator-cache-enabled` | boolean | enable caching of datastore iterators. The key is a string representing a database query, and the value is a list of tuples. Each iterator is the result of a database query, for example usersets related to a specific object, or objects related to a specific user, up to a certain number of tuples per iterator. If the request's consistency is HIGHER_CONSISTENCY, this cache is not used. | `false` |
| `checkIteratorCache.maxResults` | <div id="OPENFGA_CHECK_ITERATOR_CACHE_MAX_RESULTS"><code>OPENFGA_CHECK_ITERATOR_CACHE_MAX_RESULTS</code></div> | `check-iterator-cache-max-results` | integer | if caching of datastore iterators of Check requests is enabled, this is the limit of tuples to cache per key | `10000` |
| `checkIteratorCache.ttl` | <div id="OPENFGA_CHECK_ITERATOR_CACHE_TTL"><code>OPENFGA_CHECK_ITERATOR_CACHE_TTL</code></div> | `check-iterator-cache-ttl` | string (duration) | if caching of datastore iterators of Check requests is enabled, this is the TTL of each value | `10s` |
| `checkQueryCache.enabled` | <div id="OPENFGA_CHECK_QUERY_CACHE_ENABLED"><code>OPENFGA_CHECK_QUERY_CACHE_ENABLED</code></div> | `check-query-cache-enabled` | boolean | enable caching of Check requests. The key is a string representing a query, and the value is a boolean. For example, if you have a relation `define viewer: owner or editor`, and the query is Check(user:anne, viewer, doc:1), we'll evaluate the `owner` relation and the `editor` relation and cache both results: (user:anne, viewer, doc:1) -> allowed=true and (user:anne, owner, doc:1) -> allowed=true. The cache is stored in-memory; the cached values are overwritten on every change in the result, and cleared after the configured TTL. This flag improves latency, but turns Check and ListObjects into eventually consistent APIs. If the request's consistency is HIGHER_CONSISTENCY, this cache is not used. | `false` |
| `checkQueryCache.limit` | <div id="OPENFGA_CHECK_QUERY_CACHE_LIMIT"><code>OPENFGA_CHECK_QUERY_CACHE_LIMIT</code></div> | `check-query-cache-limit` | integer | DEPRECATED use OPENFGA_CHECK_CACHE_LIMIT. If caching of Check and ListObjects calls is enabled, this is the size limit (in items) of the cache | `10000` |
| `checkQueryCache.ttl` | <div id="OPENFGA_CHECK_QUERY_CACHE_TTL"><code>OPENFGA_CHECK_QUERY_CACHE_TTL</code></div> | `check-query-cache-ttl` | string (duration) | if caching of Check and ListObjects is enabled, this is the TTL of each value | `10s` |
| `cacheController.enabled` | <div id="OPENFGA_CACHE_CONTROLLER_ENABLED"><code>OPENFGA_CACHE_CONTROLLER_ENABLED</code></div> | `cache-controller-enabled` | boolean | enabling dynamic invalidation of check query cache and check iterator cache based on whether there are recent tuple writes. If enabled, cache will be invalidated when either 1) there are tuples written to the store OR 2) the check query cache or check iterator cache TTL has expired. | `false` |
| `cacheController.ttl` | <div id="OPENFGA_CACHE_CONTROLLER_TTL"><code>OPENFGA_CACHE_CONTROLLER_TTL</code></div> | `cache-controller-ttl` | string (duration) | if cache controller is enabled, control how frequent read changes are invoked internally to query for recent tuple writes to the store. | `10s` |
| `checkDispatchThrottling.enabled` | <div id="OPENFGA_CHECK_DISPATCH_THROTTLING_ENABLED"><code>OPENFGA_CHECK_DISPATCH_THROTTLING_ENABLED</code></div> | `check-dispatch-throttling-enabled` | boolean | enable throttling when check request's number of dispatches is high | `false` |
| `checkDispatchThrottling.frequency` | <div id="OPENFGA_CHECK_DISPATCH_THROTTLING_FREQUENCY"><code>OPENFGA_CHECK_DISPATCH_THROTTLING_FREQUENCY</code></div> | `check-dispatch-throttling-frequency` | string (duration) | the frequency period that the deprioritized throttling queue is evaluated for a check request. A higher value will result in more aggressive throttling | `10µs` |
| `checkDispatchThrottling.threshold` | <div id="OPENFGA_CHECK_DISPATCH_THROTTLING_THRESHOLD"><code>OPENFGA_CHECK_DISPATCH_THROTTLING_THRESHOLD</code></div> | `check-dispatch-throttling-threshold` | integer | define the number of recursive operations to occur before getting throttled for a check request | `100` |
| `checkDispatchThrottling.maxThreshold` | <div id="OPENFGA_CHECK_DISPATCH_THROTTLING_MAX_THRESHOLD"><code>OPENFGA_CHECK_DISPATCH_THROTTLING_MAX_THRESHOLD</code></div> | `check-dispatch-throttling-max-threshold` | integer | define the maximum dispatch threshold beyond above which requests will be throttled. 0 will use the 'dispatchThrottling.threshold' value as maximum | `0` |
| `listObjectsDispatchThrottling.enabled` | <div id="OPENFGA_LIST_OBJECTS_DISPATCH_THROTTLING_ENABLED"><code>OPENFGA_LIST_OBJECTS_DISPATCH_THROTTLING_ENABLED</code></div> | `list-objects-dispatch-throttling-enabled` | boolean | enable throttling when list objects request's number of dispatches is high | `false` |
| `listObjectsDispatchThrottling.frequency` | <div id="OPENFGA_LIST_OBJECTS_DISPATCH_THROTTLING_FREQUENCY"><code>OPENFGA_LIST_OBJECTS_DISPATCH_THROTTLING_FREQUENCY</code></div> | `list-objects-dispatch-throttling-frequency` | string (duration) | the frequency period that the deprioritized throttling queue is evaluated for a list objects request. A higher value will result in more aggressive throttling | `10µs` |
| `listObjectsDispatchThrottling.threshold` | <div id="OPENFGA_LIST_OBJECTS_DISPATCH_THROTTLING_THRESHOLD"><code>OPENFGA_LIST_OBJECTS_DISPATCH_THROTTLING_THRESHOLD</code></div> | `list-objects-dispatch-throttling-threshold` | integer | define the number of recursive operations to occur before getting throttled for a list objects request | `100` |
| `listObjectsDispatchThrottling.maxThreshold` | <div id="OPENFGA_LIST_OBJECTS_DISPATCH_THROTTLING_MAX_THRESHOLD"><code>OPENFGA_LIST_OBJECTS_DISPATCH_THROTTLING_MAX_THRESHOLD</code></div> | `list-objects-dispatch-throttling-max-threshold` | integer | define the maximum dispatch threshold beyond above which requests will be throttled for a list objects request. 0 will use the 'dispatchThrottling.threshold' value as maximum | `0` |
| `listUsersDispatchThrottling.enabled` | <div id="OPENFGA_LIST_USERS_DISPATCH_THROTTLING_ENABLED"><code>OPENFGA_LIST_USERS_DISPATCH_THROTTLING_ENABLED</code></div> | `list-users-dispatch-throttling-enabled` | boolean | enable throttling when list users request's number of dispatches is high | `false` |
| `listUsersDispatchThrottling.frequency` | <div id="OPENFGA_LIST_USERS_DISPATCH_THROTTLING_FREQUENCY"><code>OPENFGA_LIST_USERS_DISPATCH_THROTTLING_FREQUENCY</code></div> | `list-users-dispatch-throttling-frequency` | string (duration) | the frequency period that the deprioritized throttling queue is evaluated for a list users request. A higher value will result in more aggressive throttling | `10µs` |
| `listUsersDispatchThrottling.threshold` | <div id="OPENFGA_LIST_USERS_DISPATCH_THROTTLING_THRESHOLD"><code>OPENFGA_LIST_USERS_DISPATCH_THROTTLING_THRESHOLD</code></div> | `list-users-dispatch-throttling-threshold` | integer | define the number of recursive operations to occur before getting throttled for a list users request | `100` |
| `listUsersDispatchThrottling.maxThreshold` | <div id="OPENFGA_LIST_USERS_DISPATCH_THROTTLING_MAX_THRESHOLD"><code>OPENFGA_LIST_USERS_DISPATCH_THROTTLING_MAX_THRESHOLD</code></div> | `list-users-dispatch-throttling-max-threshold` | integer | define the maximum dispatch threshold beyond above which requests will be throttled for a list users request. 0 will use the 'dispatchThrottling.threshold' value as maximum | `0` |
| `requestTimeout` | <div id="OPENFGA_REQUEST_TIMEOUT"><code>OPENFGA_REQUEST_TIMEOUT</code></div> | `request-timeout` | string (duration) | The timeout duration for a request. | `3s` |

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to configure OpenFGA."
  relatedLinks={[
    {
      title: 'Configuring OpenFGA',
      description: 'Learn more about the different ways to configure OpenFGA',
      link: './configure-openfga',
      id: './configure-openfga',
    },
    {
      title: 'Running OpenFGA in Production',
      description: 'Learn the best practices of running OpenFGA in a production environment',
      link: '../../best-practices/running-in-production',
      id: './best-practices/running-in-production'
    }
  ]}
/>

<!-- End of openfga/openfga.dev/docs/content/getting-started/setup-openfga/configuration.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/setup-openfga/configure-openfga.mdx -->

---
title: Configuring OpenFGA
description: Configuring an OpenFGA Server
sidebar_position: 1
slug: /getting-started/setup-openfga/configure-openfga
---
import {
  DocumentationNotice,
  RelatedSection,
} from "@components/Docs";
import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

### Configuring OpenFGA

Refer to the [OpenFGA Getting Started](https://github.com/openfga/openfga?tab=readme-ov-file#getting-started) for info on the various ways to install OpenFGA.

The instructions below assume OpenFGA is installed and that you have the `openfga` binary in your PATH. If you have built `openfga` as a binary, but not in your path, you can refer to it directly (e.g. replace `openfga` in the instructions below with `./openfga` or `/path/to/openfga`).

For a list of all the configuration options that the latest release of OpenFGA supports, see [Configuration Options](https://github.com/openfga/openfga.dev/blob/main/./configuration.mdx), or you can run `openfga --help` to see the ones specific to your version.

:::note
The instructions below are for configuring the standalone OpenFGA server. If you are using OpenFGA as a library, you can refer to the [GoDoc](https://pkg.go.dev/github.com/openfga/openfga) for more information.
:::

#### Configuring data storage

OpenFGA supports multiple storage engine options, including:

- `memory` - A memory storage engine, which is the default. Data is lost between server restarts.
- `postgres` - A Postgres storage engine.
- `mysql` - A MySQL storage engine.
- `sqlite` - A SQLite storage engine.

The first time you run OpenFGA, or when you install a new version, you need to run the `openfga migrate` command. This will create the required database tables or perform the database migration required for a new version.

##### Postgres

```shell
openfga migrate \
    --datastore-engine postgres \
    --datastore-uri 'postgres://postgres:password@postgres:5432/postgres?sslmode=disable'

openfga run \
    --datastore-engine postgres \
    --datastore-uri 'postgres://postgres:password@postgres:5432/postgres?sslmode=disable'
```

###### PostgreSQL Read Replicas Configuration

OpenFGA supports configuring separate read and write datastores for PostgreSQL to improve performance and scalability. This feature allows you to distribute read operations across read replicas while directing write operations to the primary database.

###### Setting Up Read Replicas

To use read replicas, you need to configure both a primary datastore (for writes) and a secondary datastore (for reads):

```shell
openfga run \
    --datastore-engine postgres \
    --datastore-uri 'postgres://postgres:password@primary:5432/postgres?sslmode=disable' \
    --secondary-datastore-uri 'postgres://postgres:password@replica:5432/postgres?sslmode=disable'
```

**Important considerations:**

- The `--datastore-uri` parameter specifies the primary database (used for writes and high-consistency reads)
- The `--secondary-datastore-uri` parameter specifies the read replica (used for regular read operations)
- Both databases must have the same schema and should be kept in sync through PostgreSQL replication

###### Synchronous vs Asynchronous Replication

The choice between synchronous and asynchronous replication affects data consistency and performance:

**Synchronous Replication:**
- **Pros:** Guarantees data consistency across primary and replica
- **Cons:** Higher latency for write operations
- **Use case:** When data consistency is critical and you can tolerate slower writes
- **PostgreSQL config:** `synchronous_commit = on` and `synchronous_standby_names = 'replica_name'`

**Asynchronous Replication:**
- **Pros:** Better write performance, lower latency
- **Cons:** Potential for read-after-write inconsistencies (replica lag)
- **Use case:** When write performance is prioritized and slight delays in read consistency are acceptable
- **PostgreSQL config:** `synchronous_commit = off` (default)

###### Consistency Preferences

OpenFGA provides consistency controls to handle read-after-write scenarios:

**Higher Consistency Mode:**
When using `HIGHER_CONSISTENCY` preference in read operations, OpenFGA will automatically route the query to the primary database instead of the read replica, ensuring you get the most up-to-date data.

```javascript
// Example: Reading with higher consistency
  const { allowed } = await fgaClient.check(
      {  user: "user:anne", relation: "can_view", object: "document:roadmap"},
      {  consistency: ConsistencyPreference.HigherConsistency }
  );
```

**Default Consistency Mode:**
Regular read operations without the `HIGHER_CONSISTENCY` flag will be routed to the read replica for better performance.

###### Best Practices

1. **Monitor Replica Lag:** Set up monitoring for replication lag between primary and replica
2. **Use Higher Consistency Sparingly:** Only use `HIGHER_CONSISTENCY` when you need immediate read-after-write consistency
3. **Connection Pooling:** Configure appropriate connection pools for both primary and replica connections

###### Example PostgreSQL Replication Setup

Here's a basic example of setting up PostgreSQL streaming replication:

**Primary server configuration (postgresql.conf):**
```
wal_level = replica
max_wal_senders = 3
wal_keep_size = 64MB
synchronous_commit = on  # for synchronous replication
synchronous_standby_names = 'replica1'  # for synchronous replication
```

**Primary server authentication (pg_hba.conf):**
```
host replication replicator replica_ip/32 md5
```

**Replica server configuration (postgresql.conf):**
```
hot_standby = on
```

**Replica server recovery configuration:**
```
standby_mode = 'on'
primary_conninfo = 'host=primary_ip port=5432 user=replicator'
```

:::note
This is a simplified example. For production setups, refer to the [PostgreSQL documentation on replication](https://www.postgresql.org/docs/current/runtime-config-replication.html) for comprehensive configuration guidelines.
:::

:::caution Warning
When using asynchronous replication, be aware that read replicas might have slightly outdated data due to replication lag. Use the `HIGHER_CONSISTENCY` preference for operations that require the most recent data.
:::

To learn how to run in Docker, check our [Docker documentation](https://github.com/openfga/openfga.dev/blob/main/./docker-setup.mdx#using-postgres).

##### MySQL

The MySQL datastore has stricter limits for the max length of some fields for tuples compared to other datastore engines, in particular:

- object type is at most 128 characters (down from 256)
- object id is at most 255 characters (down from 256)
- user is at most 256 characters (down from 512)

The connection URI needs to specify the query `parseTime=true`.

```shell
openfga migrate \
    --datastore-engine mysql \
    --datastore-uri 'root:secret@tcp(mysql:3306)/openfga?parseTime=true'

openfga run \
    --datastore-engine mysql \
    --datastore-uri 'root:secret@tcp(mysql:3306)/openfga?parseTime=true'
```

To learn how to run in Docker, check our [Docker documentation](https://github.com/openfga/openfga.dev/blob/main/./docker-setup.mdx#using-mysql).

##### SQLite

```shell
openfga migrate
    --datastore-engine sqlite \
    --datastore-uri 'file:/path/to/openfga.db'

openfga run
    --datastore-engine sqlite \
    --datastore-uri 'file:/path/to/openfga.db'
```

To learn how to run in Docker, check our [Docker documentation](https://github.com/openfga/openfga.dev/blob/main/./docker-setup.mdx#using-sqlite).

#### Configuring authentication

You can configure authentication in three ways:

  - no authentication (default)
  - pre-shared key authentication
  - OIDC

##### Pre-shared key authentication

If using **Pre-shared key authentication**, you will configure OpenFGA with one or more secret keys and your application calling OpenFGA will have to set an `Authorization: Bearer <YOUR-KEY-HERE>` header.

:::caution Warning
If you are going to use this setup in production, you should enable HTTP TLS in your OpenFGA server. You will need to configure the TLS certificate and key.
:::

<Tabs groupId={"configuration"}>
<TabItem value={"configuration file"} label={"Configuration File"}>

Update the config.yaml file to

```yaml
authn:
  method: preshared
  preshared:
    keys: ["key1", "key2"]
http:
  tls:
    enabled: true
    cert: /Users/myuser/key/server.crt
    key: /Users/myuser/key/server.key
```

</TabItem>

<TabItem value={"environment variables"} label={"Environment Variables"}>

1. Configure the authentication method to preshared: `export OPENFGA_AUTHN_METHOD=preshared`.
2. Configure the authentication keys: `export OPENFGA_AUTHN_PRESHARED_KEYS=key1,key2`
3. Enable the HTTP TLS configuration: `export OPENFGA_HTTP_TLS_ENABLED=true`
4. Configure the HTTP TLS certificate location: `export OPENFGA_HTTP_TLS_CERT=/Users/myuser/key/server.crt`
5. Configure the HTTP TLS key location: `export OPENFGA_HTTP_TLS_KEY=/Users/myuser/key/server.key`

</TabItem>
</Tabs>

To learn how to run in Docker, check our [Docker documentation](https://github.com/openfga/openfga.dev/blob/main/./docker-setup.mdx#pre-shared-key-authentication).

##### OIDC

To configure with OIDC authentication, you will first need to obtain the OIDC issuer and audience from your provider.

:::caution Warning
If you are going to use this setup in production, you should enable HTTP TLS in your OpenFGA server. You will need to configure the TLS certificate and key.
:::

<Tabs groupId={"configuration"}>
<TabItem value={"configuration file"} label={"Configuration File"}>

Update the config.yaml file to

```yaml
authn:
  method: oidc
  oidc:
    issuer: "oidc-issuer" # required
    issuerAliases: "oidc-issuer-1", "oidc-issuer-2" # optional
    audience: "oidc-audience" # required
    subjects: "valid-subject-1", "valid-subject-2" # optional

http:
  tls:
    enabled: true
    cert: /Users/myuser/key/server.crt
    key: /Users/myuser/key/server.key
```

</TabItem>

<TabItem value={"environment variables"} label={"Environment Variables"}>

1. Configure the authentication method to OIDC: `export OPENFGA_AUTHN_METHOD=oidc`.
2. Configure the valid issuer (required): `export OPENFGA_AUTHN_OIDC_ISSUER=oidc-issuer`
3. Configure the valid issuer aliases (optional): `export OPENFGA_AUTHN_OIDC_ISSUER_ALIASES=oidc-issuer-1,oidc-issuer-2`
4. Configure the valid audience (required): `export OPENFGA_AUTHN_OIDC_AUDIENCE=oidc-audience`
5. Configure the valid subjects (optional): `export OPENFGA_AUTHN_OIDC_SUBJECTS=oidc-subject-1,oidc-subject-2`
6. Enable the HTTP TLS configuration: `export OPENFGA_HTTP_TLS_ENABLED=true`
7. Configure the HTTP TLS certificate location:
`export OPENFGA_HTTP_TLS_CERT=/Users/myuser/key/server.crt`
8. Configure the HTTP TLS key location:
`export OPENFGA_HTTP_TLS_KEY=/Users/myuser/key/server.key`

</TabItem>

</Tabs>

To learn how to run in Docker, check our [Docker documentation](https://github.com/openfga/openfga.dev/blob/main/./docker-setup.mdx#oidc-authentication).

#### Profiler (pprof)
:::caution Warning
Continuous profiling can be used in production deployments, but we recommend disabling it unless it is needed to troubleshoot specific performance or memory problems.
:::

Profiling through [`pprof`](https://github.com/google/pprof/blob/main/doc/README.md) can be enabled on the OpenFGA server by providing the `--profiler-enabled` flag. For example:

```sh
openfga run --profiler-enabled
```

If you need to serve the profiler on a different port than the default `3001`, you can do so by specifying the `--profiler-addr` flag. For example:

```sh
openfga run --profiler-enabled --profiler-addr :3002
```

If you want to run it in docker:
```sh
docker run -p 8080:8080 -p 8081:8081 -p 3000:3000 -p 3002:3002 openfga/openfga run --profiler-enabled --profiler-addr :3002
```

#### Health check

OpenFGA is configured with an HTTP health check endpoint `/healthz` and a gRPC health check `grpc.health.v1.Health/Check`, which is wired to datastore testing. Possible response values are
- UNKNOWN
- SERVING
- NOT_SERVING
- SERVICE_UNKNOWN

<Tabs groupId={"healthcheck"}>
<TabItem value={"health-curl"} label={"cURL"}>

```shell
curl -X GET $FGA_API_URL/healthz

# {"status":"SERVING"}
```

</TabItem>

<TabItem value={"health-grpc"} label={"gRPC"}>

```shell
# See https://github.com/fullstorydev/grpcurl#installation
grpcurl -plaintext $FGA_API_URL grpc.health.v1.Health/Check

# {"status":"SERVING"}
```
</TabItem>
</Tabs>

#### Experimental features
Various releases of OpenFGA may have experimental features that can be enabled by providing the [`--experimentals`](https://github.com/openfga/openfga.dev/blob/main/./configuration.mdx#OPENFGA_EXPERIMENTALS) flag or the `experimentals` config.

```
openfga run --experimentals="feature1, feature2"
```
or if you're using environment variables,
```
openfga -e OPENFGA_EXPERIMENTALS="feature1, feature2" run
```

The following table enumerates the experimental flags, a description of what they do, and the versions of OpenFGA the flag is supported in:

| Name                       | Description                                                        | OpenFGA Version       |
|----------------------------|--------------------------------------------------------------------|-----------------------|
| otel-metrics               | Enables support for exposing OpenFGA metrics through OpenTelemetry | `0.3.2 <= v < 0.3.5`  |
| list-objects               | Enables ListObjects API                                            | `0.2.0 <= v < 0.3.3`  |
| check-query-cache          | Enables caching of check subproblem result                         | `1.3.1 <= v < 1.3.6`  |
| enable-conditions          | Enables conditional relationship tuples                            | `1.3.8 <= v < 1.4.0`  |
| enable-modular-models      | Enables modular authorization modules                              | `1.5.1 <= v < 1.5.3`  |
| enable-list-users          | Enables new ListUsers API                                          | `1.5.4 <= v < 1.5.6`  |
| enable-consistency-params  | Enables consistency options                                        | `1.5.6 <= v < 1.6.0`  |
| enable-check-optimizations | Enables performance optimization on Check                          | `1.6.2 <= v `         |
| enable-access-control      | Enables the ability to configure and setup [access control](https://github.com/openfga/openfga.dev/blob/main/./access-control.mdx) | `1.7.0 <= v `         |

:::caution Warning
Experimental features are not guaranteed to be stable and may lead to server instabilities. It is not recommended to enable experimental features for anything other than experimentation.

Experimental feature flags are also not considered part of API compatibility and are subject to change, so please refer to each OpenFGA specific release for a list of the experimental feature flags that can be enabled for that release.
:::

#### Telemetry

OpenFGA telemetry data is collected by default starting on version `v0.3.5`. The telemetry information that is captured includes Metrics, Traces, and Logs.

:::note
Please refer to the [docker-compose.yaml](https://github.com/openfga/openfga/blob/main/docker-compose.yaml) file as an example of how to collect Metrics and Tracing in OpenFGA in a Docker environment using the [OpenTelemetry Collector](https://opentelemetry.io/docs/collector/). This should serve as a good example that you can adjust for your specific deployment scenario.
:::

#### Metrics

OpenFGA metrics are collected with the [Prometheus data format](https://prometheus.io/docs/concepts/data_model/) and exposed on address `0.0.0.0:2112/metrics`.

Metrics are exposed by default, but you can disable this with `--metrics-enabled=false` (or `OPENFGA_METRICS_ENABLED=false` environment variable).

To set an alternative address, you can provide the `--metrics-addr` flag (`OPENFGA_METRICS_ADDR` environment variable). For example:

```shell
openfga run --metrics-addr=0.0.0.0:2114
```

To see the request latency per endpoint of your OpenFGA deployment, you can provide the `--metrics-enable-rpc-histograms` flag (`OPENFGA_METRICS_ENABLE_RPC_HISTOGRAMS` environment variable).

#### Tracing

OpenFGA traces can be collected with the [OTLP data format](https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/protocol/otlp.md).

Tracing is disabled by default, but you can enable this with the `--trace-enabled=true` (`OPENFGA_TRACE_ENABLED=true` environment variable). Traces will be exported by default to address `0.0.0.0:4317`. You can change this address with the `--trace-otlp-endpoint` flag (`OPENFGA_TRACE_OTLP_ENDPOINT` environment variable). In order to just correlate `trace_id` in logs if you are propagating tracing contexts into OpenFGA, exporter can be disabled by providing `none` as endpoint value. 

To increase or decrease the trace sampling ratio, you can provide the `--trace-sample-ratio` flag (`OPENFGA_TRACE_SAMPLE_RATIO` env variable).

Tracing by default uses a insecure connection. You can enable TLS by using `--trace-otlp-tls-enabled=true` flag or the environment variable `OPENFGA_TRACE_OTLP_TLS_ENABLED`.

:::caution Warning
It is not recommended to sample all traces (e.g. `--trace-sample-ratio=1`). You will need to adjust your sampling ratio based on the amount of traffic your deployment receives. Higher traffic will require less sampling and lower traffic can tolerate higher sampling ratios.
:::

#### Logging

OpenFGA generates structured logs by default, and it can be configured with the following flags:

- `--log-format`: sets the log format. Today we support `text` and `json` format.
- `--log-level`: sets the minimum log level (defaults to `info`). It can be set to `none` to turn off logging.

:::caution Warning
It is highly recommended to enable logging in production environments. Disabling logging (`--log-level=none`) can mask important operations and hinder the ability to detect and diagnose issues, including potential security incidents. Ensure that logs are enabled and properly monitored to maintain visibility into the application's behavior and security.
:::

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to use OpenFGA."
  relatedLinks={[
    {
      title: 'Configuration Options',
      description: 'Find out all the different flags and options that OpenFGA accepts',
      link: './configuration',
      id: './configuration',
    },
    {
      title: 'Running OpenFGA in Production',
      description: 'Learn the best practices of running OpenFGA in a production environment',
      link: '../../best-practices/running-in-production',
      id: './best-practices/running-in-production',
    }
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/getting-started/setup-openfga/configure-openfga.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/setup-openfga/docker-setup.mdx -->

---
title: Docker Setup Guide
description: Setting up an OpenFGA server with Docker
sidebar_position: 2
slug: /getting-started/setup-openfga/docker
---

import {
  DocumentationNotice,
  RelatedSection,
} from "@components/Docs";
import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

### 🐳 Setup OpenFGA with Docker

<DocumentationNotice />

This article explains how to run your own OpenFGA server using Docker. To learn the different ways to configure OpenFGA check [Configuring OpenFGA](https://github.com/openfga/openfga.dev/blob/main/./configure-openfga.mdx).

#### Step by step

If you want to run OpenFGA locally as a Docker container, follow these steps:

1. [Install Docker](https://docs.docker.com/get-docker/) (if not already installed).
2. Run `docker pull openfga/openfga` to get the latest docker image.
3. Run `docker run -p 8080:8080 -p 8081:8081 -p 3000:3000 openfga/openfga run`.

This will start an HTTP server and gRPC server with the default configuration options. Port 8080 is used to serve the HTTP API, 8081 is used to serve the gRPC API, and 3000 is used for the [Playground](https://github.com/openfga/openfga.dev/blob/main/./playground.mdx).

#### Using Postgres

<Tabs groupId={"installation"}>
<TabItem value={"docker"} label={"Docker"}>

To run OpenFGA and Postgres in containers, you can create a new network to make communication between containers simpler:

```shell
docker network create openfga
```

You can then start Postgres in the network you created above:

```shell
docker run -d --name postgres --network=openfga -e POSTGRES_USER=postgres -e POSTGRES_PASSWORD=password postgres:17
```

You should now have Postgres running in a container in the `openfga` network. However, it will not have the tables required for running OpenFGA. You can use the `migrate` command to create the tables. Using the OpenFGA container, this will look like:

```shell
docker run --rm --network=openfga openfga/openfga migrate \
    --datastore-engine postgres \
    --datastore-uri "postgres://postgres:password@postgres:5432/postgres?sslmode=disable"
```

Finally, start OpenFGA:

```shell
docker run --name openfga --network=openfga -p 3000:3000 -p 8080:8080 -p 8081:8081 openfga/openfga run \
    --datastore-engine postgres \
    --datastore-uri 'postgres://postgres:password@postgres:5432/postgres?sslmode=disable'
```

</TabItem>
<TabItem value={"docker-compose"} label={"Docker Compose"}>

Copy the below code block into a local file named: `docker-compose.yaml`

```yaml
version: '3.8'

networks:
  openfga:

services:
  postgres:
    image: postgres:17
    container_name: postgres
    networks:
      - openfga
    ports:
      - "5432:5432"
    environment:
      - POSTGRES_USER=postgres
      - POSTGRES_PASSWORD=password
    healthcheck:
      test: [ "CMD-SHELL", "pg_isready -U postgres" ]
      interval: 5s
      timeout: 5s
      retries: 5

  migrate:
    depends_on:
      postgres:
        condition: service_healthy
    image: openfga/openfga:latest
    container_name: migrate
    command: migrate
    environment:
      - OPENFGA_DATASTORE_ENGINE=postgres
      - OPENFGA_DATASTORE_URI=postgres://postgres:password@postgres:5432/postgres?sslmode=disable
    networks:
      - openfga

  openfga:
    depends_on:
      migrate:
        condition: service_completed_successfully
    image: openfga/openfga:latest
    container_name: openfga
    environment:
      - OPENFGA_DATASTORE_ENGINE=postgres
      - OPENFGA_DATASTORE_URI=postgres://postgres:password@postgres:5432/postgres?sslmode=disable
      - OPENFGA_LOG_FORMAT=json
    command: run
    networks:
      - openfga
    ports:
      # Needed for the http server
      - "8080:8080"
      # Needed for the grpc server (if used)
      - "8081:8081"
      # Needed for the playground (Do not enable in prod!)
      - "3000:3000"
```

In a terminal, navigate to that directory and run:
```shell
docker-compose up
```

</TabItem>
</Tabs>

This will start the Postgres database, run `openfga migrate` to configure the database and finally start the OpenFGA server.

#### Using MySQL

<Tabs groupId={"installation_mysql"}>
<TabItem value={"docker-mysql"} label={"Docker"}>
We first make a network:

```shell
docker network create openfga
```

Then, start MySQL in the network you created above:

```shell
docker run -d --name mysql --network=openfga -e MYSQL_ROOT_PASSWORD=secret -e MYSQL_DATABASE=openfga mysql:8
```

You should now have MySQL running in a container in the `openfga` network. But we still have to migrate all the tables to be able to run OpenFGA. You can use the `migrate` command to create the tables. Using the OpenFGA container, this will look like:

```shell
docker run --rm --network=openfga openfga/openfga migrate \
    --datastore-engine mysql \
    --datastore-uri 'root:secret@tcp(mysql:3306)/openfga?parseTime=true'
```

Finally, start OpenFGA:

```shell
docker run --name openfga --network=openfga -p 3000:3000 -p 8080:8080 -p 8081:8081 openfga/openfga run \
    --datastore-engine mysql \
    --datastore-uri 'root:secret@tcp(mysql:3306)/openfga?parseTime=true'
```

</TabItem>
<TabItem value={"docker-compose-mysql"} label={"Docker Compose"}>

Copy the below code block into a local file named: `docker-compose.yaml`

```yaml
version: '3.8'

networks:
  openfga:

services:
  mysql:
    image: mysql:8
    container_name: mysql
    networks:
      - openfga
    ports:
      - "3306:3306"
    environment:
      - MYSQL_ROOT_PASSWORD=secret
      - MYSQL_DATABASE=openfga
    healthcheck:
      test: ["CMD", 'mysqladmin', 'ping', '-h', 'localhost', '-u', 'root', '-p$$MYSQL_ROOT_PASSWORD' ]
      timeout: 20s
      retries: 5

  migrate:
    depends_on:
        mysql:
            condition: service_healthy
    image: openfga/openfga:latest
    container_name: migrate
    command: migrate
    environment:
      - OPENFGA_DATASTORE_ENGINE=mysql
      - OPENFGA_DATASTORE_URI=root:secret@tcp(mysql:3306)/openfga?parseTime=true
    networks:
      - openfga

  openfga:
    depends_on:
      migrate:
        condition: service_completed_successfully
    image: openfga/openfga:latest
    container_name: openfga
    environment:
      - OPENFGA_DATASTORE_ENGINE=mysql
      - OPENFGA_DATASTORE_URI=root:secret@tcp(mysql:3306)/openfga?parseTime=true
      - OPENFGA_LOG_FORMAT=json
    command: run
    networks:
      - openfga
    ports:
      # Needed for the http server
      - "8080:8080"
      # Needed for the grpc server (if used)
      - "8081:8081"
      # Needed for the playground (Do not enable in prod!)
      - "3000:3000"
```

In a terminal, navigate to that directory and run:

```shell
docker-compose up
```

</TabItem>
</Tabs>

This will start the MySQL database, run `openfga migrate` to configure the database and finally start the OpenFGA server.

#### Using SQLite

<Tabs groupId={"installation_sqlite"}>
<TabItem value={"docker-sqlite"} label={"Docker"}>
We first make a network:

```shell
docker network create openfga
```

Then, create a volume to hold the openfga database:

```shell
docker volume create openfga
```

Next you have to migrate all the tables to be able to run OpenFGA. You can use the `migrate` command to create the tables. Using the OpenFGA container, this will look like:

```shell
docker run --rm --network=openfga \
    -v openfga:/home/nonroot \
    -u nonroot \
    openfga/openfga migrate \
    --datastore-engine sqlite \
    --datastore-uri 'file:/home/nonroot/openfga.db'
```

Finally, start OpenFGA:

```shell
docker run --name openfga --network=openfga \
    -p 3000:3000 -p 8080:8080 -p 8081:8081 \
    -v openfga:/home/nonroot \
    -u nonroot \
    openfga/openfga run \
    --datastore-engine sqlite \
    --datastore-uri 'file:/home/nonroot/openfga.db'
```

</TabItem>
<TabItem value={"docker-compose-sqlite"} label={"Docker Compose"}>

Copy the below code block into a local file named: `docker-compose.yaml`

```yaml
version: '3.8'

networks:
  openfga:

volumes:
  openfga:

services:
  migrate:
    image: openfga/openfga:latest
    container_name: migrate
    command: migrate
    user: nonroot
    environment:
      - OPENFGA_DATASTORE_ENGINE=sqlite
      - OPENFGA_DATASTORE_URI=file:/home/nonroot/openfga.db
    networks:
      - openfga
    volumes:
      - openfga:/home/nonroot

  openfga:
    depends_on:
      migrate:
        condition: service_completed_successfully
    image: openfga/openfga:latest
    container_name: openfga
    user: nonroot
    environment:
      - OPENFGA_DATASTORE_ENGINE=sqlite
      - OPENFGA_DATASTORE_URI=file:/home/nonroot/openfga.db
      - OPENFGA_LOG_FORMAT=json
    command: run
    networks:
      - openfga
    volumes:
      - openfga:/home/nonroot
    ports:
      # Needed for the http server
      - "8080:8080"
      # Needed for the grpc server (if used)
      - "8081:8081"
      # Needed for the playground (Do not enable in prod!)
      - "3000:3000"
```

In a terminal, navigate to that directory and run:

```shell
docker-compose up
```

</TabItem>
</Tabs>

This will create a new `openfga` volume to store the SQLite database, run `openfga migrate` to configure the database and finally start the OpenFGA server.

#### Pre-shared key authentication

To configure with pre-shared authentication and enabling TLS in http server with Docker.

1. Copy the certificate and key files to your Docker container.
2. Run with the following command:
```shell
docker run --name openfga --network=openfga -p 3000:3000 -p 8080:8080 -p 8081:8081 openfga/openfga run \
    --authn-method=preshared \
    --authn-preshared-keys="key1,key2" \
    --http-tls-enabled=true \
    --http-tls-cert="/Users/myuser/key/server.crt" \
    --http-tls-key="/Users/myuser/key/server.key"
```

#### OIDC authentication

To configure with OIDC authentication and enabling TLS in http server with Docker.

1. Copy the certificate and key files to your docker container.
2. Run the following command

```shell
docker run --name openfga --network=openfga -p 3000:3000 -p 8080:8080 -p 8081:8081 openfga/openfga run \
    --authn-method=oidc \
    --authn-oidc-issuer="oidc-issuer" \
    --authn-oidc-audience="oidc-audience" \
    --http-tls-enabled=true \
    --http-tls-cert="/Users/myuser/key/server.crt" \
    --http-tls-key="/Users/myuser/key/server.key"
```

#### Enabling profiling

If you are enabling profiling, make sure you enable the corresponding port in docker. The default port is `3001`, but if you need to serve the profiler on a different port, you can do so by specifying the `--profiler-addr` flag. For example:

```sh
docker run -p 8080:8080 -p 8081:8081 -p 3000:3000 -p 3002:3002 openfga/openfga run --profiler-enabled --profiler-addr :3002
```

#### Related sections

<RelatedSection
  description="Check the following sections for more on how to use OpenFGA."
  relatedLinks={[
    {
      title: 'Running OpenFGA in Production',
      description: 'Learn the best practices of running OpenFGA in a production environment',
      link: '../../best-practices/running-in-production',
      id: './best-practices/running-in-production',    }
  ]}
/>



<!-- End of openfga/openfga.dev/docs/content/getting-started/setup-openfga/docker-setup.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/setup-openfga/kubernetes-setup.mdx -->

---
title: Kubernetes Setup Guide
description: Setting up an OpenFGA server with Kubernetes
sidebar_position: 2
slug: /getting-started/setup-openfga/kubernetes
---

import {
  DocumentationNotice,
  RelatedSection,
} from "@components/Docs";
import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

### ☸️ Setup OpenFGA with Kubernetes
To deploy OpenFGA into a Kubernetes environment you can use the official [OpenFGA Helm chart](https://artifacthub.io/packages/helm/openfga/openfga). Please refer to the official documentation on Artifact Hub for the Helm chart for more instructions.



<!-- End of openfga/openfga.dev/docs/content/getting-started/setup-openfga/kubernetes-setup.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/setup-openfga/overview.mdx -->

---
title: Setup OpenFGA
description: Setting up an OpenFGA server
sidebar_position: 1
slug: /getting-started/setup-openfga/overview
---

import {
  DocumentationNotice,
  CardGrid,
} from "@components/Docs";

### Setup OpenFGA
Follow the guides below to set up an OpenFGA server.

<DocumentationNotice />

<CardGrid
  middle={[
    {
      title: 'Configure an OpenFGA Server',
      description: 'How to setup an OpenFGA server.',
      to: 'configure-openfga',
    },
    {
      title: 'Docker Setup Guide',
      description: 'How to setup an OpenFGA server with Docker.',
      to: 'docker',
    },
    {
      title: 'Kubernetes Setup Guide',
      description: 'How to setup an OpenFGA server with Kubernetes.',
      to: 'kubernetes',
    },
    {
      title: 'Setup Access Control',
      description: 'How to enable and setup the built-in access control OpenFGA server (experimental).',
      to: 'access-control',
    }
  ]}
/>

<!-- End of openfga/openfga.dev/docs/content/getting-started/setup-openfga/overview.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/setup-openfga/playground.mdx -->

---
title: Using the OpenFGA Playground 
description: Setting up an OpenFGA server
sidebar_position: 4
slug: /getting-started/setup-openfga/playground
---
### Using the OpenFGA Playground 

The Playground facilitates rapid development by allowing you to visualize and model your application's authorization models and manage relationship tuples with a locally running OpenFGA instance.

It is enabled on port 3000 by default and accessible at http://localhost:3000/playground. 

The Playground is designed for early prototyping and learning. It has several limitations:

- It works by embedding the public [Playground website](https://play.fga.dev) in an `<iframe>`. To do this securely, the public Playground configures its Content Security Policies to enable running it from `localhost`. **You can't run the Playground in a host different than `localhost`.
- It does not support OIDC authentication. 
- It loads up to 100 tuples.
- It does not support conditional tuples or contextual tuples.

We have [the intention](https://github.com/openfga/roadmap/issues/7) to rewrite the Playground code and open source it, which will make it possible to overcome some of those limitations. 

However, we recommend that for managing a production OpenFGA deployment, you use the [Visual Studio Code integration](https://github.com/openfga/vscode-ext), [OpenFGA CLI](https://github.com/openfga/cli), combined with the ability to specify a model + tuples + assertions in [.fga.yaml](https://github.com/openfga/cli#run-tests-on-an-authorization-model) files.

#### Running the Playground in a different port

You can change the playground port using the `--playground-port` flag. For example,

```sh
openfga run --playground-enabled --playground-port 3001
```

#### Disabling the Playground

As the Playground allows performing any action in the OpenFGA server, it's not recommended to have it enabled in production deployments.

To run OpenFGA with the Playground disabled, provide the `--playground-enabled=false` flag.

```shell
openfga run --playground-enabled=false
```

<!-- End of openfga/openfga.dev/docs/content/getting-started/setup-openfga/playground.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/cli.mdx -->

---
title: Use the FGA CLI
description: Use the FGA CLI
sidebar_position: 10
slug: /getting-started/cli
---

import { DocumentationNotice, RelatedSection, ProductName, ProductNameFormat } from '@components/Docs';

### Use the FGA CLI

The <ProductName format={ProductNameFormat.ShortForm}/> Command Line Interface (CLI) enables you to interact with an FGA store, where you can manage tasks, create stores, and update FGA models, among other actions. For more information on FGA stores, see [What Is A Store](https://github.com/openfga/openfga.dev/blob/main/../concepts.mdx#what-is-a-store).

For instructions on installing it, visit the [OpenFGA CLI Github repository](https://github.com/openfga/cli).

#### Configuration

The CLI is configured to use a specific FGA server in one of three ways:

- Using CLI flags.
- Using environment variables.
- Storing configuration values in a .fga.yaml located in the user’s root directory.

The API Url setting needs to point to the OpenFGA server:

| Name    | Flag      | Environment | ~/.fga.yaml | Default Value           |
| ------- | --------- | ----------- | ----------- | ----------------------- |
| API Url | --api-url | FGA_API_URL | api-url     | `http://localhost:8080` |

If you use [pre-shared key authentication](https://github.com/openfga/openfga.dev/blob/main/./setup-openfga/configure-openfga.mdx#pre-shared-key-authentication), provide the following parameters which appends the pre-shared key in the HTTP request header:
| Name           | Flag               | Environment          | ~/.fga.yaml      |
| -------------- | ------------------ | -------------------- | ---------------- |
| API Token      | --api-token        | FGA_API_TOKEN        | api-token        |


If you use [OIDC authentication](https://github.com/openfga/openfga.dev/blob/main/./setup-openfga/configure-openfga.mdx#oidc), configure the following parameters based on the OIDC server that’s used to issue tokens:

| Name           | Flag               | Environment          | ~/.fga.yaml      |
| -------------- | ------------------ | -------------------- | ---------------- |
| Client ID      | --client-id        | FGA_CLIENT_ID        | client-id        |
| Client Secret  | --client-secret    | FGA_CLIENT_SECRET    | client-secret    |
| Scopes         | --api-scopes       | FGA_API_SCOPES       | api-scopes       |
| Token Issuer   | --api-token-issuer | FGA_API_TOKEN_ISSUER | api-token-issuer |
| Token Audience | --api-audience     | FGA_API_AUDIENCE     | api-audience     |

A default store Id and authorization model Id can also be configured:

| Name                   | Flag       | Environment  | ~/.fga.yaml |
| ---------------------- | ---------- | ------------ | ----------- |
| Store ID               | --store-id | FGA_STORE_ID | store-id    |
| Authorization Model ID | --model-id | FGA_MODEL_ID | model-id    |

All of the examples in this document assume the CLI is properly configured and that the Store ID is set either in an environment variable or the `~/.fga.yaml` file.

#### Basic operations

The CLI commands below show you how to create a store and run your application’s most common operations, including how to write a model and write/delete/read tuples, and run queries.

```bash

# Create a store with a model
$ fga store create --model docs.fga
{
  "store": {
    "created_at":"2024-02-09T23:20:28.637533296Z",
    "id":"01HP82R96XEJX1Q9YWA9XRQ4PM",
    "name":"docs",
    "updated_at":"2024-02-09T23:20:28.637533296Z"
  },
  "model": {
    "authorization_model_id":"01HP82R97B448K89R45PW7NXD8"
  }
}

# Keep the returned store id in an environment variable
$ export FGA_STORE_ID=01HP82R96XEJX1Q9YWA9XRQ4PM

# Get the latest model
$ fga model get
model
  schema 1.1

type user

type organization
  relations
    define admin: [user with non_expired_grant]
    define member: [user]

type document
  relations
    define editor: admin from organization
    define organization: [organization]
    define viewer: editor or member from organization

condition non_expired_grant(current_time: timestamp, grant_duration: duration, grant_time: timestamp) {
  current_time < grant_time + grant_duration

}

# Write a tuple
$ fga tuple write user:anne member organization:acme
{
  "successful": [
    {
      "object":"organization:acme",
      "relation":"member",
      "user":"user:anne"
    }
  ]
}

# Read all tuples. It returns the one added above
$ fga tuple read
{
  "continuation_token":"",
  "tuples": [
    {
      "key": {
        "object":"organization:acme",
        "relation":"member",
        "user":"user:anne"
      },
      "timestamp":"2024-02-09T23:05:43.586Z"
    }
  ]
}

# Write another tuple, adding a document for the acme organization
$ fga tuple write organization:acme organization document:readme
{
  "successful": [
    {
      "object":"document:readme",
      "relation":"organization",
      "user":"organization:acme"
    }
  ]
}

# Check if anne can view the document.
# Anne can view it as she's a member of organization:acme, which is the organization that owns the document
$ fga query check user:anne viewer document:readme
{
  "allowed":true,
  "resolution":""
}

# List all the documents user:anne can view
$ fga query list-objects user:anne viewer document
{
  "objects": [
    "document:readme"
  ]
}

# List all the relations that user:anne has with document:readme
$ fga query list-relations user:anne document:readme
{
  "relations": [
    "viewer"
  ]
}

# Delete user:anne as a member of organization:acme
$ fga tuple delete user:anne member organization:acme
{}

# Verify that user:anne is no longer a viewer of document:readme
$ fga query check user:anne viewer document:readme
{
  "allowed":false,
  "resolution":""
}
```

#### Work with authorization model versions

<ProductName format={ProductNameFormat.ShortForm} /> models are [immutable](https://github.com/openfga/openfga.dev/blob/main/../getting-started/immutable-models.mdx);
each time a model is written to a store, a new version of the model is created.

All <ProductName format={ProductNameFormat.ShortForm}/> API endpoints receive an optional authorization model ID that points to a specific version of the model and defaults to the latest model version. Always use a specific model ID and update it each time a new model version is used in production.

The following CLI commands lists the model Ids and find the latest one:

```shell
# List all the authorization models
$ fga model list
{
  "authorization_models": [
    {
      "id":"01HPJ8JZV091THNTDFE2SFYNNJ",
      "created_at":"2024-02-13T22:14:50Z"
    },
    {
      "id":"01HPJ808Q8J56QMK4WNT7MG7P7",
      "created_at":"2024-02-13T22:04:37Z"
    },
    {
      "id":"01HPJ7YKNV0QT0S6CFRJMK231P",
      "created_at":"2024-02-13T22:03:43Z"
    }
  ]
}

# List the last model, displaying just the model ID
$ fga model get --field id
# Model ID: 01HPJ8JZV091THNTDFE2SFYNNJ

# List the last model, displaying just the model ID, in JSON format, to make it simpler to parse
$ fga model get --field id --format json
{
  "id":"01HPJ8JZV091THNTDFE2SFYNNJ"
}
```

When using the CLI, the model ID can be specified as a `--model-id` parameter or as part of the configuration.

#### Import tuples

To import tuples, use the`fga tuple write` command. It has the following parameters:

| Parameter                                            | Description                                                                                                          |
| ---------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| --file                                               | Specifies the file name json, yaml and csv files are supported                                                       |
| --max-tuples-per-write (optional, default=1, max=40) | Maximum number of tuples to send in a single write                                                                   |
| --max-parallel-requests (optional, default=4)        | Maximum number of requests to send in parallel. Make it larger if you want to import a large number of tuples faster |
| --hide-imported-tuples (optional, default=false)     | Hide successful imports from output, useful when importing large datasets                                            |

The CLI returns a detailed JSON response that includes:

- `successful`: List of successfully written tuples (hidden when using `--hide-imported-tuples`)
- `failed`: List of tuples that failed to write, including the error reason
- `total_count`: Total number of tuples processed in this operation
- `successful_count`: Number of tuples successfully written
- `failed_count`: Number of tuples that failed to write

When using `--hide-imported-tuples`, the successful tuples are not included in the output, making it more practical when importing large datasets. Failed tuples are always shown to help identify and fix any issues. If you specify `--max-tuples-per-write` greater than one, an error in one of the tuples implies none of the tuples get written.

```bash
$ fga tuple write --file tuples.yaml

{
  "successful": [
    {
      "object":"organization:acme",
      "relation":"member",
      "user":"user:anne"
    }
  ],
  "failed":null,
  "total_count": 1,
  "successful_count": 1,
  "failed_count": 0
}

$ fga tuple write --file tuples.yaml
{
  "successful":null,
  "failed": [
      {
        "tuple_key": {
          "object":"organization:acme",
          "relation":"member",
          "user":"user:anne"
        },
      "reason":"Write validation error for POST Write with body {\"code\":\"write_failed_due_to_invalid_input\",\"message\":\"cannot write a tuple which already exists: user: 'user:anne', relation: 'member', object: 'organization:acme': invalid write input\"}\n with error code write_failed_due_to_invalid_input error message: cannot write a tuple which already exists: user: 'user:anne', relation: 'member', object: 'organization:acme': invalid write input"
    }
  ],
  "total_count": 1,
  "successful_count": 0,
  "failed_count": 1
}
```

Below are examples of the different file formats the CLI accepts when writing tuples:

##### yaml

```yaml
- user: user:peter
  relation: admin
  object: organization:acme
  condition:
    name: non_expired_grant
    context:
      grant_time: '2024-02-01T00:00:00Z'
      grant_duration: 1h
- user: user:anne
  relation: member
  object: organization:acme
```

##### JSON

```
[
    {
        "user": "user:peter",
        "relation": "admin",
        "object": "organization:acme",
        "condition": {
            "context": {
                "grant_duration": "1h",
                "grant_time": "2024-02-01T00:00:00Z"
            },
            "name": "non_expired_grant"
        }
    },
    {
        "user": "user:anne",
        "relation": "member",
        "object": "organization:acme"
    }
]
```

##### CSV

```csv
user_type,user_id,user_relation,relation,object_type,object_id,condition_name,condition_context
user,anne,member,,organization,acme,,
user,peter1,admin,,organization,acme,non_expired_grant,"{""grant_duration"": ""1h"", ""grant_time"": ""2024-02-01T00:00:00Z""}"
```

When using the CSV format, you can omit certain headers, and you don’t need to specify the value for those fields.

#### Delete Tuples

To delete a tuple, specify the user/relation/object you want to delete. To delete a group of tuples, specify a file that contains those tuples. Supported file formats are `json`, `yaml` and `csv`.

```bash
$ fga tuple delete --file tuples.yaml
{
  "successful": [
    {
      "object":"organization:acme",
      "relation":"admin",
      "user":"user:peter"
    },
    {
      "object":"organization:acme",
      "relation":"member",
      "user":"user:anne"
    }
  ],
  "failed":null
}
```

Delete all tuples from a store by reading all the tuples first and then deleting them:

```bash
# Reads all the tuples and outputs them in a json format that can be used by 'fga tuple delete' and 'fga tuple write'.

$ fga tuple read --output-format=simple-json --max-pages 0    > tuples.json
$ fga tuple delete --file  tuples.json
```

#### Import stores

The CLI can import an [FGA Test file](https://github.com/openfga/openfga.dev/blob/main/../modeling/testing-models.mdx) in a store. It writes the model included and imports the tuples in the fga test file.

Given the following `.fga.yaml` file:

```yaml
model: |
  model
    schema 1.1

  type user
  type organization
   relations
     define member : [user]
  }

tuples:
  # Anne is a member of the Acme organization
  - user: user:anne
    relation: member
    object: organization:acme
```

The following command is used to import the file contents in a store:

```bash
$ fga store import --file <filename>.fga.yaml
```

Use the `fga model get` command is used to verify that the model was correctly written, and the `fga tuple read` command is used to verify that the tuples were properly imported.

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to learn how to write tests."
  relatedLinks={[
    {
      title: 'Testing Models',
      description: 'Learn how to test FGA models using the FGA CLI.',
      link: '../modeling/testing',
      id: '../modeling/testing-models.mdx',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/getting-started/cli.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/configure-model.mdx -->

---
title: Configure Authorization Model
description: Configuring authorization model for a store
slug: /getting-started/configure-model
---

import {
  AuthzModelSnippetViewer,
  DocumentationNotice,
  languageLabelMap,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  SdkSetupPrerequisite,
  SupportedLanguage,
  WriteAuthzModelViewer,
} from '@components/Docs';
import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

### Configure Authorization Model for a Store

<DocumentationNotice />

This article explains how to configure an <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" /> for a <ProductConcept section="what-is-a-store" linkName="store" /> in an OpenFGA server.

#### Before you start

<Tabs groupId="languages">
<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx), [created the store](https://github.com/openfga/openfga.dev/blob/main/./create-store.mdx) and [setup the SDK client](https://github.com/openfga/openfga.dev/blob/main/./setup-sdk-client.mdx).
3. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>
<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx), [created the store](https://github.com/openfga/openfga.dev/blob/main/./create-store.mdx) and [setup the SDK client](https://github.com/openfga/openfga.dev/blob/main/./setup-sdk-client.mdx).
3. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.DOTNET_SDK} label={languageLabelMap.get(SupportedLanguage.DOTNET_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx), [created the store](https://github.com/openfga/openfga.dev/blob/main/./create-store.mdx) and [setup the SDK client](https://github.com/openfga/openfga.dev/blob/main/./setup-sdk-client.mdx).
3. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.PYTHON_SDK} label={languageLabelMap.get(SupportedLanguage.PYTHON_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx), [created the store](https://github.com/openfga/openfga.dev/blob/main/./create-store.mdx) and [setup the SDK client](https://github.com/openfga/openfga.dev/blob/main/./setup-sdk-client.mdx).
3. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.JAVA_SDK} label={languageLabelMap.get(SupportedLanguage.JAVA_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx), [created the store](https://github.com/openfga/openfga.dev/blob/main/./create-store.mdx) and [setup the SDK client](https://github.com/openfga/openfga.dev/blob/main/./setup-sdk-client.mdx).
3. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.CLI} label={languageLabelMap.get(SupportedLanguage.CLI)}>

1. <SdkSetupPrerequisite />
2. You have [installed the CLI](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx), [created the store](https://github.com/openfga/openfga.dev/blob/main/./create-store.mdx) and [setup your environment variables](https://github.com/openfga/openfga.dev/blob/main/./setup-sdk-client.mdx).
3. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.CURL} label={languageLabelMap.get(SupportedLanguage.CURL)}>

1. <SdkSetupPrerequisite />
2. You have [created the store](https://github.com/openfga/openfga.dev/blob/main/./create-store.mdx) and have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>
</Tabs>

#### Step by step

Assume that you want to configure your store with the following model.

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          reader: {
            this: {},
          },
          writer: {
            this: {},
          },
          owner: {
            this: {},
          },
        },
        metadata: {
          relations: {
            reader: { directly_related_user_types: [{ type: 'user' }] },
            writer: { directly_related_user_types: [{ type: 'user' }] },
            owner: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

To configure authorization model, we can invoke the [write authorization models API](https://github.com/openfga/openfga.dev/blob/main//api/service#Authorization%20Models/WriteAuthorizationModel).

<WriteAuthzModelViewer
  authorizationModel={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          reader: {
            this: {},
          },
          writer: {
            this: {},
          },
          owner: {
            this: {},
          },
        },
        metadata: {
          relations: {
            reader: { directly_related_user_types: [{ type: 'user' }] },
            writer: { directly_related_user_types: [{ type: 'user' }] },
            owner: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
  allowedLanguages={[
    SupportedLanguage.JS_SDK,
    SupportedLanguage.GO_SDK,
    SupportedLanguage.DOTNET_SDK,
    SupportedLanguage.PYTHON_SDK,
    SupportedLanguage.JAVA_SDK,
    SupportedLanguage.CLI,
    SupportedLanguage.CURL,
  ]}
/>

The API will then return the authorization model ID.

:::info Note
The OpenFGA API only accepts an authorization model in the API&#39;s JSON syntax.

To convert between the API Syntax and the friendly DSL, you can use the [FGA CLI](https://github.com/openfga/cli/).
:::

#### Related Sections

<RelatedSection
  description="Take a look at the following sections for more information on how to configure authorization model in your store."
  relatedLinks={[
    {
      title: 'Getting Started with Modeling',
      description: 'Read how to get started with modeling.',
      link: '../modeling/getting-started',
    },
    {
      title: 'Modeling: Direct Relationships',
      description: 'Read the basics of modeling authorization and granting access to users.',
      link: '../modeling/direct-access',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/getting-started/configure-model.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/configure-telemetry.mdx -->

---
title: Configure SDK Client Telemetry
description: How to configure your SDK Client to collect telemetry using OpenTelemetry.
slug: /getting-started/configure-telemetry
---

import {
  AuthzModelSnippetViewer,
  DocumentationNotice,
  languageLabelMap,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  SdkSetupPrerequisite,
  SupportedLanguage,
  WriteAuthzModelViewer,
} from '@components/Docs';
import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

### Configure SDK Client Telemetry

<DocumentationNotice />

The <ProductName format={ProductNameFormat.ShortForm}/> SDK Client supports telemetry data collection using [OpenTelemetry](https://opentelemetry.io).

#### Enabling Telemetry

1. [Install the <ProductName format={ProductNameFormat.ShortForm}/> SDK Client](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx)
2. [Setup OpenTelemetry](https://opentelemetry.io/docs/getting-started/)
3. Install the OpenTelemetry SDK dependencies for your application
4. Instantiate the OpenTelemetry SDK in your application

Once you have completed these steps, the <ProductName format={ProductNameFormat.ShortForm}/> SDK Client will automatically collect telemetry data using your application's OpenTelemetry configuration.

#### Customizing Telemetry

The <ProductName format={ProductNameFormat.ShortForm}/> SDK Client will automatically use [a default configuration](#supported-metrics) for telemetry collection. You can provide your own configuration to include additional metrics or to exclude metrics that are not relevant to your application.

<Tabs groupId="languages">
<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

```typescript
import 'dotenv/config';
import { OpenFgaClient, TelemetryAttribute, TelemetryConfiguration, TelemetryMetric } from '@openfga/sdk';

const telemetryConfig = {
  metrics: {
    [TelemetryMetric.CounterCredentialsRequest]: {
      attributes: new Set([
        TelemetryAttribute.UrlScheme,
        TelemetryAttribute.UserAgentOriginal,
        TelemetryAttribute.HttpRequestMethod,
        TelemetryAttribute.FgaClientRequestClientId,
        TelemetryAttribute.FgaClientRequestStoreId,
        TelemetryAttribute.FgaClientRequestModelId,
        TelemetryAttribute.HttpRequestResendCount,
      ]),
    },
    [TelemetryMetric.HistogramRequestDuration]: {
      attributes: new Set([
        TelemetryAttribute.HttpResponseStatusCode,
        TelemetryAttribute.UserAgentOriginal,
        TelemetryAttribute.FgaClientRequestMethod,
        TelemetryAttribute.FgaClientRequestClientId,
        TelemetryAttribute.FgaClientRequestStoreId,
        TelemetryAttribute.FgaClientRequestModelId,
        TelemetryAttribute.HttpRequestResendCount,
      ]),
    },
    [TelemetryMetric.HistogramQueryDuration]: {
      attributes: new Set([
        TelemetryAttribute.HttpResponseStatusCode,
        TelemetryAttribute.UserAgentOriginal,
        TelemetryAttribute.FgaClientRequestMethod,
        TelemetryAttribute.FgaClientRequestClientId,
        TelemetryAttribute.FgaClientRequestStoreId,
        TelemetryAttribute.FgaClientRequestModelId,
        TelemetryAttribute.HttpRequestResendCount,
      ]),
    },
  },
};

const fgaClient = new OpenFgaClient({
  telemetry: telemetryConfig,
  // ...
});
```

</TabItem>

<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

```go
import (
  "github.com/openfga/go-sdk/client"
  "github.com/openfga/go-sdk/telemetry"
)

otel := telemetry.Configuration{
  Metrics: &telemetry.MetricsConfiguration{
    METRIC_COUNTER_CREDENTIALS_REQUEST: &telemetry.MetricConfiguration{
      ATTR_FGA_CLIENT_REQUEST_CLIENT_ID: &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_HTTP_REQUEST_METHOD:          &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_FGA_CLIENT_REQUEST_MODEL_ID:  &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_FGA_CLIENT_REQUEST_STORE_ID:  &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_FGA_CLIENT_RESPONSE_MODEL_ID: &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_HTTP_HOST:                    &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_HTTP_REQUEST_RESEND_COUNT:    &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_HTTP_RESPONSE_STATUS_CODE:    &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_URL_FULL:                     &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_URL_SCHEME:                   &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_USER_AGENT_ORIGINAL:          &telemetry.AttributeConfiguration{Enabled: true},
    },
    METRIC_HISTOGRAM_REQUEST_DURATION: &telemetry.MetricConfiguration{
      ATTR_FGA_CLIENT_REQUEST_CLIENT_ID: &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_HTTP_REQUEST_METHOD:          &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_FGA_CLIENT_REQUEST_MODEL_ID:  &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_FGA_CLIENT_REQUEST_STORE_ID:  &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_FGA_CLIENT_RESPONSE_MODEL_ID: &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_HTTP_HOST:                    &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_HTTP_REQUEST_RESEND_COUNT:    &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_HTTP_RESPONSE_STATUS_CODE:    &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_URL_FULL:                     &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_URL_SCHEME:                   &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_USER_AGENT_ORIGINAL:          &telemetry.AttributeConfiguration{Enabled: true},
    },
    METRIC_HISTOGRAM_QUERY_DURATION: &telemetry.MetricConfiguration{
      ATTR_FGA_CLIENT_REQUEST_CLIENT_ID: &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_HTTP_REQUEST_METHOD:          &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_FGA_CLIENT_REQUEST_MODEL_ID:  &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_FGA_CLIENT_REQUEST_STORE_ID:  &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_FGA_CLIENT_RESPONSE_MODEL_ID: &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_HTTP_HOST:                    &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_HTTP_REQUEST_RESEND_COUNT:    &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_HTTP_RESPONSE_STATUS_CODE:    &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_URL_FULL:                     &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_URL_SCHEME:                   &telemetry.AttributeConfiguration{Enabled: true},
      ATTR_USER_AGENT_ORIGINAL:          &telemetry.AttributeConfiguration{Enabled: true},
    },
  },
}

fgaClient, err := client.NewSdkClient(&client.ClientConfiguration{
  Telemetry: &otel,
  // ...
})
```

</TabItem>

<TabItem value={SupportedLanguage.DOTNET_SDK} label={languageLabelMap.get(SupportedLanguage.DOTNET_SDK)}>

```csharp
using OpenFga.Sdk.Client;
using OpenFga.Sdk.Configuration;
using OpenFga.Sdk.Telemetry;

TelemetryConfig telemetryConfig = new TelemetryConfig() {
    Metrics = new Dictionary<string, MetricConfig> {
        [TelemetryMeter.RequestDuration] = new () {
            Attributes = new HashSet<string> {
                TelemetryAttribute.HttpStatus,
                TelemetryAttribute.HttpUserAgent,
                TelemetryAttribute.RequestMethod,
                TelemetryAttribute.RequestClientId,
                TelemetryAttribute.RequestStoreId,
                TelemetryAttribute.RequestModelId,
                TelemetryAttribute.RequestRetryCount,
            },
        },
    },
};

var configuration = new ClientConfiguration {
    Telemetry = telemetryConfig,
    // ...
};

var fgaClient = new OpenFgaClient(configuration);
```

</TabItem>

<TabItem value={SupportedLanguage.PYTHON_SDK} label={languageLabelMap.get(SupportedLanguage.PYTHON_SDK)}>

```python
from openfga_sdk import (
  ClientConfiguration,
  OpenFgaClient,
)

telemetry_config: dict[str, dict[str, dict[str, bool]]] = {
  "metrics": {
    "fga-client.request.duration": {
      "fga-client.request.model_id": False,
      "fga-client.response.model_id": False,
      "fga-client.user": True,
      "http.client.request.duration": True,
      "http.server.request.duration": True,
    },
  },
}

configuration = ClientConfiguration(
  telemetry=telemetry_config,
  // ...
)

with OpenFgaClient(configuration) as fga_client:
  # ...
```

</TabItem>

<TabItem value={SupportedLanguage.JAVA_SDK} label={languageLabelMap.get(SupportedLanguage.JAVA_SDK)}>

```java
import dev.openfga.sdk.api.client.ApiClient;
import dev.openfga.sdk.api.configuration.ClientConfiguration;
import dev.openfga.sdk.api.configuration.TelemetryConfiguration;

Map<Attribute, Optional<Object>> attributes = new HashMap<>();
attributes.put(Attributes.FGA_CLIENT_REQUEST_CLIENT_ID, Optional.empty());
attributes.put(Attributes.FGA_CLIENT_REQUEST_METHOD, Optional.empty());
attributes.put(Attributes.FGA_CLIENT_REQUEST_MODEL_ID, Optional.empty());
attributes.put(Attributes.FGA_CLIENT_REQUEST_STORE_ID, Optional.empty());
attributes.put(Attributes.FGA_CLIENT_RESPONSE_MODEL_ID, Optional.empty());
attributes.put(Attributes.HTTP_HOST, Optional.empty());
attributes.put(Attributes.HTTP_REQUEST_METHOD, Optional.empty());
attributes.put(Attributes.HTTP_REQUEST_RESEND_COUNT, Optional.empty());
attributes.put(Attributes.HTTP_RESPONSE_STATUS_CODE, Optional.empty());
attributes.put(Attributes.URL_FULL, Optional.empty());
attributes.put(Attributes.URL_SCHEME, Optional.empty());
attributes.put(Attributes.USER_AGENT, Optional.empty());

Map<Metric, Map<Attribute, Optional<Object>>> metrics = new HashMap<>();
metrics.put(Counters.CREDENTIALS_REQUEST, attributes);
metrics.put(Histograms.QUERY_DURATION, attributes);
metrics.put(Histograms.REQUEST_DURATION, attributes);

ClientConfiguration config = new ClientConfiguration()
  // ...
  .telemetryConfiguration(new TelemetryConfiguration(metrics);

OpenFgaClient fgaClient = new OpenFgaClient(config);
```

</TabItem>
</Tabs>

#### Examples

We provide example applications for using telemetry with the <ProductName format={ProductNameFormat.ShortForm}/> SDK Client.

- [Node.js](https://github.com/openfga/js-sdk/tree/main/example/opentelemetry)
- [Go](https://github.com/openfga/go-sdk/tree/main/example/opentelemetry)
- [.NET](https://github.com/openfga/dotnet-sdk/tree/main/example/OpenTelemetryExample)
- [Python](https://github.com/openfga/python-sdk/tree/main/example/opentelemetry)

#### Supported Metrics

The <ProductName format={ProductNameFormat.ShortForm}/> SDK Client can collect the following metrics:

| Metric Name                      | Type      | Enabled by Default | Description                                                                       |
| -------------------------------- | --------- | ------------------ | --------------------------------------------------------------------------------- |
| `fga-client.request.duration`    | Histogram | Yes                | Total request time for FGA requests, in milliseconds                              |
| `fga-client.query.duration`      | Histogram | Yes                | Time taken by the FGA server to process and evaluate the request, in milliseconds |
| `fga-client.credentials.request` | Counter   | Yes                | Total number of new token requests initiated using the Client Credentials flow    |

#### Supported Attributes

The <ProductName format={ProductNameFormat.ShortForm}/> SDK Client can collect the following attributes:

| Attribute Name                 | Type   | Enabled by Default | Description                                                                       |
| ------------------------------ | ------ | ------------------ | --------------------------------------------------------------------------------- |
| `fga-client.request.client_id` | string | Yes                | Client ID associated with the request, if any                                     |
| `fga-client.request.method`    | string | Yes                | FGA method/action that was performed (e.g., Check, ListObjects) in TitleCase      |
| `fga-client.request.model_id`  | string | Yes                | Authorization model ID that was sent as part of the request, if any               |
| `fga-client.request.store_id`  | string | Yes                | Store ID that was sent as part of the request                                     |
| `fga-client.response.model_id` | string | Yes                | Authorization model ID that the FGA server used                                   |
| `fga-client.user`              | string | No                 | User associated with the action of the request for check and list users           |
| `http.client.request.duration` | int    | No                 | Duration for the SDK to complete the request, in milliseconds                     |
| `http.host`                    | string | Yes                | Host identifier of the origin the request was sent to                             |
| `http.request.method`          | string | Yes                | HTTP method for the request                                                       |
| `http.request.resend_count`    | int    | Yes                | Number of retries attempted, if any                                               |
| `http.response.status_code`    | int    | Yes                | Status code of the response (e.g., `200` for success)                             |
| `http.server.request.duration` | int    | No                 | Time taken by the FGA server to process and evaluate the request, in milliseconds |
| `url.scheme`                   | string | Yes                | HTTP scheme of the request (`http`/`https`)                                       |
| `url.full`                     | string | Yes                | Full URL of the request                                                           |
| `user_agent.original`          | string | Yes                | User Agent used in the query                                                      |

#### Tracing

OpenFGA [supports](https://github.com/openfga/openfga.dev/blob/main/./setup-openfga/configure-openfga.mdx) tracing with OpenTelemetry.

If your application uses OpenTelemetry tracing, traces will be propagated to OpenFGA, provided the traces are exported to the same address.
This can be useful to help diagnose any suspected performance issues when using OpenFGA.

If your application does not already use tracing, OpenTelemetry offers [zero-code instrumentation](https://opentelemetry.io/docs/zero-code/) for several languages.
For example, a TypeScript application can be configured with tracing by using one of the [OpenTelemetry JavaScript Instrumentation Libraries](https://opentelemetry.io/docs/languages/js/libraries/):

```bash
npm install --save @opentelemetry/auto-instrumentations-node
```

Create an initialization file to configure tracing:

```javascript
// tracing.ts
import { NodeSDK } from '@opentelemetry/sdk-node';
import { OTLPTraceExporter } from '@opentelemetry/exporter-trace-otlp-http';
import { getNodeAutoInstrumentations } from '@opentelemetry/auto-instrumentations-node';

const sdk = new NodeSDK({
  traceExporter: new OTLPTraceExporter(),
  // registers all instrumentation packages, you may wish to change this
  instrumentations: [getNodeAutoInstrumentations()],
});

sdk.start();
```

Run the application with the appropriate OTEL environment variables:

```bash
OTEL_SERVICE_NAME='YOUR-SERVICE-NAME' ts-node -r ./tracing.ts YOUR-APP.ts
```

See the [OpenTelemetry documentation](https://opentelemetry.io/docs/) for additional information to configure your application for tracing.

<!-- End of openfga/openfga.dev/docs/content/getting-started/configure-telemetry.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/create-store.mdx -->

---
title: Create a Store
description: Creating a store
slug: /getting-started/create-store
---

import {
    SupportedLanguage,
    languageLabelMap,
    DocumentationNotice,
} from '@components/Docs';
import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

### Create a Store

<DocumentationNotice />

A [store](https://github.com/openfga/openfga.dev/blob/main/../concepts.mdx#what-is-a-store) is a OpenFGA entity that contains your authorization data. You will need to create a store in OpenFGA before adding an [authorization model](https://github.com/openfga/openfga.dev/blob/main/../concepts.mdx#what-is-an-authorization-model) and [relationship tuples](https://github.com/openfga/openfga.dev/blob/main/../concepts.mdx#what-is-a-relationship-tuple) to it.

This article explains how to set up an OpenFGA store.

#### Step by step

<Tabs groupId="languages">
<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

```javascript
const { OpenFgaClient } = require('@openfga/sdk'); // OR import { OpenFgaClient } from '@openfga/sdk';

const openFga = new OpenFgaClient({
    apiUrl: process.env.FGA_API_URL, // required, e.g. https://api.fga.example
});

const { id: storeId } = await openFga.createStore({
    name: "FGA Demo Store",
});
```

</TabItem>
<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

```go
import (
    "context"
    "os"

    . "github.com/openfga/go-sdk/client"
)

func main() {
    fgaClient, err := NewSdkClient(&ClientConfiguration{
        ApiUrl:               os.Getenv("FGA_API_URL"), // required, e.g. https://api.fga.example
        StoreId:              os.Getenv("FGA_STORE_ID"), // optional, not needed for \`CreateStore\` and \`ListStores\`, required before calling for all other methods
        AuthorizationModelId: os.Getenv("FGA_MODEL_ID"),  // Optional, can be overridden per request
    })

    if err != nil {
        // .. Handle error
    }

    resp, err := fgaClient.CreateStore(context.Background()).Body(ClientCreateStoreRequest{Name: "FGA Demo"}).Execute()
    if err != nil {
        // .. Handle error
    }
}
```

</TabItem>

<TabItem value={SupportedLanguage.DOTNET_SDK} label={languageLabelMap.get(SupportedLanguage.DOTNET_SDK)}>

```dotnet
using OpenFga.Sdk.Client;
using OpenFga.Sdk.Client.Model;
using OpenFga.Sdk.Model;
using Environment = System.Environment;

namespace ExampleApp;

class MyProgram {
    static async Task Main() {
         var configuration = new ClientConfiguration() {
            ApiUrl = Environment.GetEnvironmentVariable("FGA_API_URL") ?? "http://localhost:8080", // required, e.g. https://api.fga.example
            StoreId = Environment.GetEnvironmentVariable("FGA_STORE_ID"), // optional, not needed for \`CreateStore\` and \`ListStores\`, required before calling for all other methods
            AuthorizationModelId = Environment.GetEnvironmentVariable("FGA_MODEL_ID"), // optional, can be overridden per request
        };
        var fgaClient = new OpenFgaClient(configuration);

        var store = await fgaClient.CreateStore(new ClientCreateStoreRequest(){Name = "FGA Demo Store"});
    }
}
```

</TabItem>

<TabItem value={SupportedLanguage.PYTHON_SDK} label={languageLabelMap.get(SupportedLanguage.PYTHON_SDK)}>

```python
import asyncio
import os
import openfga_sdk
from openfga_sdk.client import OpenFgaClient
from openfga_sdk.models.create_store_request import CreateStoreRequest

async def main():
    configuration = openfga_sdk.ClientConfiguration(
        api_url = os.environ.get('FGA_API_URL'), # required, e.g. https://api.fga.example
    )

    async with OpenFgaClient(configuration) as fga_client:
        body = CreateStoreRequest(
            name = "FGA Demo Store",
        )
        response = await fga_client.create_store(body)

asyncio.run(main())
```

</TabItem>

<TabItem value={SupportedLanguage.JAVA_SDK} label={languageLabelMap.get(SupportedLanguage.JAVA_SDK)}>

```java
import dev.openfga.sdk.api.client.OpenFgaClient;
import dev.openfga.sdk.api.configuration.ClientConfiguration;
import dev.openfga.sdk.api.model.CreateStoreRequest;

public class Example {
    public static void main(String[] args) {
        var config = new ClientConfiguration()
                .apiUrl(System.getenv("FGA_API_URL")) // If not specified, will default to "https://localhost:8080"
                .storeId(System.getenv("FGA_STORE_ID")) // Not required when calling createStore() or listStores()
                .authorizationModelId(System.getenv("FGA_AUTHORIZATION_MODEL_ID")); // Optional, can be overridden per request

        var fgaClient = new OpenFgaClient(config);
        var body = new CreateStoreRequest().name("FGA Demo Store");
        var store = fgaClient.createStore(body).get();
    }
}
```

</TabItem>

<TabItem value={SupportedLanguage.CLI} label={languageLabelMap.get(SupportedLanguage.CLI)}>

```shell
fga store create --name "FGA Demo Store"

# To create the store and directly put the Store ID into an env variable:
# export FGA_STORE_ID=$(fga store create --name "FGA Demo Store" | jq -r .store.id)
```

</TabItem>

<TabItem value={SupportedLanguage.CURL} label={languageLabelMap.get(SupportedLanguage.CURL)}>

```shell
curl -X POST $FGA_API_URL/stores \
  -H "content-type: application/json" \
  -d '{"name": "FGA Demo Store"}'
```

</TabItem>

</Tabs>


<!-- End of openfga/openfga.dev/docs/content/getting-started/create-store.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/framework.mdx -->

---
title: Integrate Within a Framework
sidebar_position: 5
slug: /getting-started/framework
description: Integrating FGA within a framework, such as Fastify or Fiber

---

import {
  SupportedLanguage,
  languageLabelMap,
  DocumentationNotice,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  SdkSetupPrerequisite,
} from '@components/Docs';
import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

### Integrate Within a Framework

<DocumentationNotice />

This section will illustrate how to integrate <ProductName format={ProductNameFormat.LongForm}/> within a framework, such as [Fastify](https://www.fastify.io/) or [Fiber](https://docs.gofiber.io/).

#### Before you start

<Tabs groupId="languages">
<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the OpenFGA SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
4. You know how to [perform a Check](https://github.com/openfga/openfga.dev/blob/main/./perform-check.mdx).
5. You have loaded `FGA_API_URL` and `FGA_STORE_ID` as environment variables.

</TabItem>
<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the OpenFGA SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
4. You know how to [perform a Check](https://github.com/openfga/openfga.dev/blob/main/./perform-check.mdx).
5. You have loaded `FGA_API_URL` and `FGA_STORE_ID` as environment variables.

</TabItem>
</Tabs>

#### Step by step

Assume that you want to have a web service for `document`s using one of the frameworks mentioned above. The service will authenticate users via [JWT tokens](https://auth0.com/docs/secure/tokens/json-web-tokens), which contain the user ID.

:::caution Note
The reader should set up their own `login` method based on their OpenID connect provider's documentation.
:::

Assume that you want to provide a route `GET /read/{document}` to return documents depending on whether the authenticated user has access to it.

##### 01. Install and setup framework

The first step is to install the framework.

<Tabs groupId="languages">
<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

For the context of this example, we will use the [Fastify framework](https://www.fastify.io/). For that we need to install the following packages:

- the [`fastify`](https://github.com/fastify/fastify) package that provides the framework itself
- the [`fastify-plugin`](https://github.com/fastify/fastify-plugin) package that allows integrating plugins with Fastify
- the [`fastify-jwt`](https://github.com/fastify/fastify-jwt) package for processing JWT tokens

Using [npm](https://www.npmjs.com):

```shell
npm install fastify fastify-plugin fastify-jwt
```

Using [yarn](https://yarnpkg.com):

```shell
yarn add fastify fastify-plugin fastify-jwt
```

Next, we setup the web service with the `GET /read/{document}` route in file `app.js`.

```javascript
// Require the framework and instantiate it
const fastify = require('fastify')({ logger: true });

// Declare the route
fastify.get('/read/:document', async (request, reply) => {
  return { read: request.params.document };
});

// Run the server
const start = async () => {
  try {
    await fastify.listen(3000);
  } catch (err) {
    fastify.log.error(err);
    process.exit(1);
  }
};
start();
```

</TabItem>
<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

For the context of this example, we will use the [Fiber framework](https://docs.gofiber.io/). For that we need to install the following Go packages:

- the [`gofiber/fiber`](https://docs.gofiber.io/) package that provides the Fiber framework itself
- the [`gofiber/jwt`](https://github.com/gofiber/jwt) middleware authentication layer for JWT
- the [`golang-jwt`](https://github.com/golang-jwt/jwt) package that provides Go support for JWT

```shell
go get -u github.com/gofiber/fiber/v2 github.com/gofiber/jwt/v3 github.com/golang-jwt/jwt/v4
```

Next, we setup the web service with the `GET /read/{document}` route.

```go
package main

import "github.com/gofiber/fiber/v2"

func main() {
  app := fiber.New()

  app.Get("/read/:document", read)

  app.Listen(":3000")
}

func read(c *fiber.Ctx) error {
  return c.SendString(c.Params("document"))
}
```

</TabItem>
</Tabs>

##### 02. Authenticate and get user ID

Before we can call <ProductName format={ProductNameFormat.LongForm}/> to protect the `/read/{document}` route, we need to validate the user's JWT.

<Tabs groupId="languages">
<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

The `fastify-jwt` package allows validation of JWT tokens, as well as providing access to the user's identity.

In `jwt-authenticate.js`:

```javascript
const fp = require('fastify-plugin');

module.exports = fp(async function (fastify, opts) {
  fastify.register(require('fastify-jwt'), {
    secret: {
      private: readFileSync(`${path.join(__dirname, 'certs')}/private.key`, 'utf8'),
      public: readFileSync(`${path.join(__dirname, 'certs')}/public.key`, 'utf8'),
    },
    sign: { algorithm: 'RS256' },
  });

  fastify.decorate('authenticate', async function (request, reply) {
    try {
      await request.jwtVerify();
    } catch (err) {
      reply.send(err);
    }
  });
});
```

Then, use the `preValidation` hook of a route to protect it and access the user information inside the JWT:

In `route-read.js`:

```javascript
module.exports = async function (fastify, opts) {
  fastify.get(
    '/read/:document',
    {
      preValidation: [fastify.authenticate],
    },
    async function (request, reply) {
      // the user's id is in request.user
      return { read: request.params.document };
    },
  );
};
```

Finally, update `app.js` to register the newly added hooks.

```javascript
const fastify = require('fastify')({ logger: true });
const jwtAuthenticate = require('./jwt-authenticate');
const routeread = require('./route-read');

fastify.register(jwtAuthenticate);
fastify.register(routeread);

// Run the server!
const start = async () => {
  try {
    await fastify.listen(3000);
  } catch (err) {
    fastify.log.error(err);
    process.exit(1);
  }
}
start();

```

</TabItem>
<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

We will now setup middleware to authenticate the incoming JWTs.

```go
package main

import (
  "crypto/rand"
  "crypto/rsa"
  "log"

  "github.com/gofiber/fiber/v2"

  jwtware "github.com/gofiber/jwt/v3"
  "github.com/golang-jwt/jwt/v4"
)

var (
  // Do not do this in production.
  // In production, you would have the private key and public key pair generated
  // in advance. NEVER add a private key to any GitHub repo.
  privateKey *rsa.PrivateKey
)

func main() {
  app := fiber.New()

  // Just as a demo, generate a new private/public key pair on each run.
  rng := rand.Reader
  var err error
  privateKey, err = rsa.GenerateKey(rng, 2048)
  if err != nil {
    log.Fatalf("rsa.GenerateKey: %v", err)
  }

  // JWT Middleware
  app.Use(jwtware.New(jwtware.Config{
    SigningMethod: "RS256",
    SigningKey:    privateKey.Public(),
  }))

  app.Get("/read/:document", read)

  app.Listen(":3000")
}

func read(c *fiber.Ctx) error {
  user := c.Locals("user").(*jwt.Token)
  claims := user.Claims.(jwt.MapClaims)
  name := claims["name"].(string)
  return c.SendString(name + " read " + c.Params("document"))
}
```

</TabItem>
</Tabs>

##### 03. Integrate the <ProductName format={ProductNameFormat.ShortForm}/> check API into the service

<Tabs groupId="languages">
<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

First, we will create a decorator `preauthorize` to parse the incoming HTTP method as well as name of the document, and set the appropriate `relation` and `object` that we will call Check on.

In `preauthorize.js`:

```javascript
const fp = require('fastify-plugin');

module.exports = fp(async function (fastify, opts) {
  fastify.decorate('preauthorize', async function (request, reply) {
    try {
      switch (request.method) {
        case 'GET':
          request.relation = 'reader';
          break;
        case 'POST':
          request.relation = 'writer';
          break;
        case 'DELETE':
        default:
          request.relation = 'owner';
          break;
      }
      request.object = `document:${request.params.document}`;
    } catch (err) {
      reply.send(err);
    }
  });
});
```

Next, we will create a decorator called `authorize`. This decorator will invoke the [Check API](https://github.com/openfga/openfga.dev/blob/main/./perform-check.mdx) to see if the user has a relationship with the specified document.

In `authorize.js`:

```javascript
const fp = require('fastify-plugin');
const { OpenFgaClient } = require('@openfga/sdk'); // OR import { OpenFgaClient } from '@openfga/sdk';

module.exports = fp(async function (fastify, opts) {
  fastify.decorate('authorize', async function (request, reply) {
    try {
      // configure the openfga api client
      const fgaClient = new OpenFgaClient({
        apiUrl: process.env.FGA_API_URL, // required, e.g. https://api.fga.example
        storeId: process.env.FGA_STORE_ID,
      });
      const { allowed } = await fgaClient.check({
        user: request.user,
        relation: request.relation,
        object: request.object,
      });
      if (!allowed) {
        reply.code(403).send(`forbidden`);
      }
    } catch (err) {
      reply.send(err);
    }
  });
});
```

We can now update the `GET /read/{document}` route to check for user permissions.

In `route-read.js`:

```javascript
module.exports = async function (fastify, opts) {
  fastify.get(
    '/read/:document',
    {
      preValidation: [fastify.authenticate, fastify.preauthorize, fastify.authorize],
    },
    async function (request, reply) {
      // the user's id is in request.user
      return { read: request.params.document };
    },
  );
};
```

Finally, we will register the new hooks in `app.js`:

```javascript
const fastify = require('fastify')({ logger: true });
const jwtAuthenticate = require('./jwt-authenticate');
const preauthorize = require('./preauthorize');
const authorize = require('./authorize');
const routeread = require('./route-read');

fastify.register(jwtAuthenticate);
fastify.register(preauthorize);
fastify.register(authorize);
fastify.register(routeread);

const start = async () => {
  try {
    await fastify.listen(3000);
  } catch (err) {
    fastify.log.error(err);
    process.exit(1);
  }
}
start();
```

</TabItem>
<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

We will create two middlewares:

- `preauthorize` will parse the user's JWT and prepare variables needed to call Check API.
- `checkAuthorization` will call the [`Check API`](https://github.com/openfga/openfga.dev/blob/main/./perform-check.mdx) to see if the user has a relationship with the specified document.

```go
package main

import (
  "context"
  "crypto/rand"
  "crypto/rsa"
  "log"
  "os"

  "github.com/gofiber/fiber/v2"

  jwtware "github.com/gofiber/jwt/v3"
  "github.com/golang-jwt/jwt/v4"
  . "github.com/openfga/go-sdk/client"
)

var (
  // Do not do this in production.
  // In production, you would have the private key and public key pair generated
  // in advance. NEVER add a private key to any GitHub repo.
  privateKey *rsa.PrivateKey
)

func main() {
  app := fiber.New()

  // Just as a demo, generate a new private/public key pair on each run.
  rng := rand.Reader
  var err error
  privateKey, err = rsa.GenerateKey(rng, 2048)
  if err != nil {
    log.Fatalf("rsa.GenerateKey: %v", err)
  }

  // JWT Middleware
  app.Use(jwtware.New(jwtware.Config{
    SigningMethod: "RS256",
    SigningKey:    privateKey.Public(),
  }))

  app.Use("/read/:document", preauthorize)

  app.Use(checkAuthorization)

  app.Get("/read/:document", read)

  app.Listen(":3000")
}

func read(c *fiber.Ctx) error {
  user := c.Locals("user").(*jwt.Token)
  claims := user.Claims.(jwt.MapClaims)
  name := claims["name"].(string)
  return c.SendString(name + " read " + c.Params("document"))
}

func preauthorize(c *fiber.Ctx) error {
  // get the user name from JWT
  user := c.Locals("user").(*jwt.Token)
  claims := user.Claims.(jwt.MapClaims)
  name := claims["name"].(string)
  c.Locals("username", name)

  // parse the HTTP method
  switch (c.Method()) {
    case "GET":
      c.Locals("relation", "reader")
    case "POST":
      c.Locals("relation", "writer")
    case "DELETE":
      c.Locals("relation", "owner")
    default:
      c.Locals("relation", "owner")
  }

  // get the object name and prepend with type name "document:"
  c.Locals("object", "document:" + c.Params("document"))
  return c.Next()
}

// Middleware to check whether user is authorized to access document
func checkAuthorization(c *fiber.Ctx) error {
  fgaClient, err := NewSdkClient(&ClientConfiguration{
    ApiUrl:               os.Getenv("FGA_API_URL"), // required, e.g. https://api.fga.example
    StoreId:        os.Getenv("FGA_STORE_ID"), // optional, not needed for \`CreateStore\` and \`ListStores\`, required before calling for all other methods
    AuthorizationModelId: os.Getenv("FGA_MODEL_ID"),  // optional, can be overridden per request
  })

  if err != nil {
    return fiber.NewError(fiber.StatusServiceUnavailable, "Unable to build OpenFGA client")
  }

  body := ClientCheckRequest{
    User: c.Locals("username").(string),
    Relation: c.Locals("relation").(string),
    Object: c.Locals("object").(string),
  }
  data, err := fgaClient.Check(context.Background()).Body(body).Execute()

  if err != nil {
    return fiber.NewError(fiber.StatusServiceUnavailable, "Unable to check for authorization")
  }

  if !(*data.Allowed) {
    return fiber.NewError(fiber.StatusForbidden, "Forbidden to access document")
  }

  // Go to the next middleware
  return c.Next()
}
```

</TabItem>

</Tabs>

#### Related Sections

<RelatedSection
  description="Take a look at the following sections for examples that you can try when integrating with SDK."
  relatedLinks={[
    {
      title: 'Entitlements',
      description: 'Modeling Entitlements for a System in {ProductName}.',
      link: '../modeling/advanced/entitlements',
    },
    {
      title: 'IoT',
      description: 'Modeling Fine-Grained Authorization for an IoT Security Camera System with {ProductName}.',
      link: '../modeling/advanced/iot',
    },
    {
      title: 'Slack',
      description: 'Modeling Authorization for Slack with {ProductName}.',
      link: '../modeling/advanced/slack',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/getting-started/framework.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/immutable-models.mdx -->

---
title: Immutable Authorization Models
slug: /getting-started/immutable-models
description: Learn how to take advantage of the immutable properties of Authorization Models
---

import {
  DocumentationNotice,
  ProductName,
  ProductNameFormat,
  RelatedSection,
} from '@components/Docs';

### Immutable Authorization Models

<DocumentationNotice />

Authorization Models in <ProductName format={ProductNameFormat.ShortForm}/> are immutable, they are created once and then can no longer be deleted or modified. Each time you write an authorization model, a new version is created.

#### Viewing all the authorization models

You can list all the authorization models for a store using the [ReadAuthorizationModels](https://github.com/openfga/openfga.dev/blob/main//api/service#/Authorization%20Models/ReadAuthorizationModels) API. This endpoint returns the results sorted in reverse chronological order, as in the first model in the list is the latest model. By default, only the last 50 models are returned, but you can paginate across by passing in the appropriate `continuation_token`.

#### How to target a particular model

Some endpoints relating to tuples ([Check](https://github.com/openfga/openfga.dev/blob/main//api/service#/Relationship%20Queries/Check), [ListObjects](https://github.com/openfga/openfga.dev/blob/main//api/service#/Relationship%20Queries/ListObjects), [ListUsers](https://github.com/openfga/openfga.dev/blob/main//api/service#/Relationship%20Queries/ListUsers), [Expand](https://github.com/openfga/openfga.dev/blob/main//api/service#/Relationship%20Queries/Expand), [Write](https://github.com/openfga/openfga.dev/blob/main//api/service#/Relationship%20Tuples/Write)) accept an `authorization_model_id`, which we strongly recommend passing, especially in production.

In practice, you would pin the authorization model ID alongside the store ID in your configuration management system. Your services would read this value and use it in their requests to FGA. This helps you ensure that your services are using the same consistent ID across all your applications, and that rollouts can be seamless.

#### Benefits of passing in an authorization model ID

Targeting a specific model ID would ensure that you don't accidentally break your authorization checks in production because a mistake was made when updating the authorization model. It would also slightly improve the latency on your check requests.

If that field is passed, evaluation and validation will happen for that particular authorization model ID. If this field is not passed, <ProductName format={ProductNameFormat.ShortForm}/> will use the last created Authorization Model for that store.

#### Potential use-cases

##### Complex model migrations

Certain model changes require adapting your application code and migrating tuples before rolling it out. For example, if you rename a relation, you need to change the application and copy the existing tuples to use the new relation name. This scenario requires the following steps:

- Update the authorization model with the renamed relation. A new model ID will be generated but it won't be used in production yet.
- Update the application to use the new relation name.
- Copy existing tuples to use the new relation name.
- Deploy the new application targeting the new model ID.

You can learn more about model migrations [here](https://github.com/openfga/openfga.dev/blob/main/../modeling/migrating/overview.mdx).

##### Progresivelly rollout changes

Being able to target multiple versions of the authorization model enables you to progressively roll out model changes, which is something you should consider doing if the changes are significant. You could:

- Do shadow checks where you would perform checks against both your existing model and the new upcoming model you are hoping to replace it with.This will help you detect and resolve any accidental discrepancies you may be introducing, and ensure that your new model is at least as good as your old one.

- When you are confident with your model, you could implement gradual rollouts that would allow you to monitor and check if any users are having access issues before you go ahead and increase the rollout to 100% of your user base.

:::info Getting an Authorization Model's Creation Date
The Authorization Model ID is a [ULID](https://github.com/ulid/spec) which includes the date created. You can extract the creation date using a library for your particular language.

For example, in JavaScript you can do the following:

```js
import ulid = require('ulid');

const time = ulid.decodeTime(id);
```

:::

#### Related Sections

<RelatedSection
  description="Learn more about modeling and production usage in {ProductName}."
  relatedLinks={[
    {
      title: 'Configuration Language',
      description: 'Learn about the {ProductName} Configuration Language.',
      link: '../configuration-language',
      id: '../configuration-language',
    },
    {
      title: 'Getting Started with Modeling',
      description: 'Read how to get started with modeling.',
      link: '../modeling/getting-started',
    },
    {
      title: 'Data and API Best Practices',
      description: 'Learn the best practices for managing data and invoking APIs in production environment',
      link: './tuples-api-best-practices',
      id: './tuples-api-best-practices',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/getting-started/immutable-models.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/install-sdk.mdx -->

---
title: Install SDK Client
sidebar_position: 2
slug: /getting-started/install-sdk
description: Installing SDK client
---

import {
  SupportedLanguage,
  languageLabelMap,
  DocumentationNotice,
  ProductName,
  ProductNameFormat,
  RelatedSection,
} from '@components/Docs';
import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

### Install SDK Client

<DocumentationNotice />

To get started, install the <ProductName format={ProductNameFormat.ShortForm}/> SDK packages.

<Tabs groupId="languages">
<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

You can find the Node.js package on npm at: [@openfga/sdk](https://www.npmjs.com/package/@openfga/sdk).

Using [npm](https://www.npmjs.com/):

<!-- markdown-link-check-disable -->

```shell
npm install @openfga/sdk
```

Using [yarn](https://yarnpkg.com):

```shell
yarn add @openfga/sdk
```

</TabItem>
<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

You can find the Go package on GitHub at: [@openfga/go-sdk](https://github.com/openfga/go-sdk).

To install:

```
go get -u github.com/openfga/go-sdk
```

In your code, import the module and use it:

```go
import (
    openfga "github.com/openfga/go-sdk"
)

func main() {
    configuration, err := openfga.NewConfiguration(openfga.Configuration{
        ApiUrl:               os.Getenv("FGA_API_URL"), // required, e.g. https://api.fga.example
    })

    if err != nil {
        // .. Handle error
    }
}
```

You can then run

```shell
go mod tidy
```

to update `go.mod` and `go.sum` if you are using them.

</TabItem>
<TabItem value={SupportedLanguage.DOTNET_SDK} label={languageLabelMap.get(SupportedLanguage.DOTNET_SDK)}>

<!-- markdown-link-check-enable -->

The <ProductName format={ProductNameFormat.ShortForm}/> .NET SDK is available on [NuGet](https://www.nuget.org/packages/OpenFga.Sdk).

You can install it using:

- The [dotnet CLI](https://docs.microsoft.com/en-us/nuget/consume-packages/install-use-packages-dotnet-cli):

```powershell
dotnet add package OpenFGA.Sdk
```

- The [Package Manager Console](https://docs.microsoft.com/en-us/nuget/consume-packages/install-use-packages-powershell) inside Visual Studio:

```powershell
Install-Package OpenFGA.Sdk
```

- [Visual Studio](https://docs.microsoft.com/en-us/nuget/consume-packages/install-use-packages-visual-studio), [Visual Studio for Mac](https://docs.microsoft.com/en-us/visualstudio/mac/nuget-walkthrough) and [IntelliJ Rider](https://www.jetbrains.com/help/rider/Using_NuGet.html): Search for and install `OpenFGA.Sdk` in each of their respective package manager UIs.

</TabItem>
<TabItem value={SupportedLanguage.PYTHON_SDK} label={languageLabelMap.get(SupportedLanguage.PYTHON_SDK)}>

The <ProductName format={ProductNameFormat.ShortForm}/> Python SDK is available on [PyPI](https://pypi.org/project/openfga-sdk).

To install:

```
pip3 install openfga_sdk
```

In your code, import the module and use it:

```python
import openfga_sdk
```

</TabItem>
<TabItem value={SupportedLanguage.JAVA_SDK} label={languageLabelMap.get(SupportedLanguage.JAVA_SDK)}>

You can find the Java package on [Maven Central](https://central.sonatype.com/artifact/dev.openfga/openfga-sdk).

Using [Maven](https://maven.apache.org/):

```
<dependency>
    <groupId>dev.openfga</groupId>
    <artifactId>openfga-sdk</artifactId>
    <version>0.3.1</version>
</dependency>
```

Using [Gradle](https://gradle.org/):

```groovy
implementation 'dev.openfga:openfga-sdk:0.3.1'
```

</TabItem>
<TabItem value={SupportedLanguage.CLI} label={languageLabelMap.get(SupportedLanguage.CLI)}>

The <ProductName format={ProductNameFormat.ShortForm}/> CLI is available on [GitHub](https://github.com/openfga/cli).

To install:

##### Brew
```shell
brew install openfga/tap/fga
```

##### Linux (deb, rpm and apk) packages
Download the .deb, .rpm or .apk packages from the [releases page](https://github.com/openfga/cli/releases).

Debian:
```shell
sudo apt install ./fga_<version>_linux_<arch>.deb
```

Fedora:
```shell
sudo dnf install ./fga_<version>_linux_<arch>.rpm
```

Alpine Linux:
```shell
sudo apk add --allow-untrusted ./fga_<version>_linux_<arch>.apk
```

##### Docker
```shell
docker pull openfga/cli; docker run -it openfga/cli
```

##### Go

```shell
go install github.com/openfga/cli/cmd/fga@latest
```

##### Manually
Download the pre-compiled binaries from the [releases page](https://github.com/openfga/cli/releases).

</TabItem>
</Tabs>

#### Related Sections

<RelatedSection
  description="Get {ProductName}'s SDKs to add authorization to your API."
  relatedLinks={[
    {
      title: '{ProductName} Node.js SDK',
      description: 'Install our Node.js & JavaScript SDK to get started.',
      link: 'https://www.npmjs.com/package/@openfga/sdk',
    },
    {
      title: '{ProductName} Go SDK',
      description: 'Use our Go SDK to easily connect your Go application to the {ProductName} API',
      link: 'https://github.com/openfga/go-sdk',
    },
    {
      title: '{ProductName} .NET SDK',
      description: 'Connect your .NET service with {ProductName} using our .NET SDK',
      link: 'https://github.com/openfga/dotnet-sdk',
    },
    {
      title: '{ProductName} Python SDK',
      description: 'Connect your Python service with {ProductName} using our Python SDK',
      link: 'https://github.com/openfga/python-sdk',
    },
    {
      title: '{ProductName} Java SDK',
      description: 'Connect your Java service with {ProductName} using our Java SDK',
      link: 'https://github.com/openfga/java-sdk',
    }
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/getting-started/install-sdk.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/overview.mdx -->

---
id: overview
title: 'Getting Started'
slug: /getting-started
sidebar_position: 0
---

import { DocumentationNotice, IntroCard, CardGrid, ProductName } from '@components/Docs';

<DocumentationNotice />

The following will provide a step-by-step guide on how to get started with <ProductName />.

<IntroCard
  title="When to use"
  description="This section is useful if you understand the basic concepts of {ProductName}, and want to learn how to get started."
/>

### Content

<CardGrid
  middle={[
    {
      title: 'Setup OpenFGA',
      description: 'How to setup an OpenFGA server.',
      to: 'getting-started/setup-openfga/overview',
    },
    {
      title: 'Install SDK Client',
      description: 'Install the SDK for the language of your choice.',
      to: 'getting-started/install-sdk',
    },
    {
      title: 'Create a Store',
      description: 'Creating an OpenFGA entity that owns an authorization model and relationship tuples.',
      to: 'getting-started/create-store',
    },
    {
      title: 'Setup SDK Client for Store',
      description: 'Configure the SDK client for your store.',
      to: 'getting-started/setup-sdk-client',
    },
    {
      title: 'Configure Authorization Model',
      description: 'Programmatically configure authorization model for an {ProductName} store.',
      to: 'getting-started/configure-model',
    },
    {
      title: 'Update Relationship Tuples',
      description: 'Programmatically write authorization data to an {ProductName} store.',
      to: 'getting-started/update-tuples',
    },
    {
      title: 'Perform a Check',
      description: 'Programmatically perform an authorization check against an {ProductName} store.',
      to: 'getting-started/perform-check',
    },
    {
      title: 'Perform a List Objects Request',
      description: 'Programmatically perform a list objects request against an {ProductName} store.',
      to: 'getting-started/perform-list-objects',
    },
    {
      title: 'Integrate Within a Framework',
      description: 'Integrate authorization checks with a framework.',
      to: 'getting-started/framework',
    },
    {
      title: 'Immutable Authorization Models',
      description: 'Learn how to take advantage of the immutable properties of Authorization Models in {ProductName}.',
      to: 'getting-started/immutable-models',
    },
    {
      title: 'Best Practices',
      description: 'Best Practices for implementing OpenFGA.',
      to: '../docs/best-practices',
    }
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/getting-started/overview.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/perform-check.mdx -->

---
title: Perform a Check
sidebar_position: 4
toc_max_heading_level: 4
slug: /getting-started/perform-check
description: Checking if a user is authorized to perform an action on a resource
---

import {
  SupportedLanguage,
  languageLabelMap,
  CheckRequestViewer,
  BatchCheckRequestViewer,
  DocumentationNotice,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  SdkSetupHeader,
  SdkSetupPrerequisite,
} from '@components/Docs';
import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

### Perform a Check

<DocumentationNotice />

This section will illustrate how to perform a <ProductConcept section="what-is-a-check-request" linkName="check" /> request to determine whether a <ProductConcept section="what-is-a-user" linkName="user" /> has a certain <ProductConcept section="what-is-a-relationship" linkName="relationship" /> with an <ProductConcept section="what-is-an-object" linkName="object" />.

#### Before you start

<Tabs groupId="languages">

<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.DOTNET_SDK} label={languageLabelMap.get(SupportedLanguage.DOTNET_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.PYTHON_SDK} label={languageLabelMap.get(SupportedLanguage.PYTHON_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.JAVA_SDK} label={languageLabelMap.get(SupportedLanguage.JAVA_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.CLI} label={languageLabelMap.get(SupportedLanguage.CLI)}>

1. <SdkSetupPrerequisite />
2. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx).
3. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.CURL} label={languageLabelMap.get(SupportedLanguage.CURL)}>

1. <SdkSetupPrerequisite />
2. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
3. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>
</Tabs>

#### Step by step

Assume that you want to check whether user `anne` has relationship `reader` with object `document:Z`

##### 01. Configure the <ProductName format={ProductNameFormat.ShortForm}/> API client

Before calling the check API, you will need to configure the API client.

<Tabs groupId="languages">
<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.JS_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.GO_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.DOTNET_SDK} label={languageLabelMap.get(SupportedLanguage.DOTNET_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.DOTNET_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.PYTHON_SDK} label={languageLabelMap.get(SupportedLanguage.PYTHON_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.PYTHON_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.JAVA_SDK} label={languageLabelMap.get(SupportedLanguage.JAVA_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.JAVA_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.CLI} label={languageLabelMap.get(SupportedLanguage.CLI)}>

<SdkSetupHeader lang={SupportedLanguage.CLI} />

</TabItem>
<TabItem value={SupportedLanguage.CURL} label={languageLabelMap.get(SupportedLanguage.CURL)}>

To obtain the [access token](https://auth0.com/docs/get-started/authentication-and-authorization-flow/call-your-api-using-the-client-credentials-flow):

<SdkSetupHeader lang={SupportedLanguage.CURL} />

</TabItem>
</Tabs>

##### 02. Calling Check API

To check whether user `user:anne` has relationship `can_view` with object `document:Z`

<CheckRequestViewer
  user={'user:anne'}
  relation={'can_view'}
  object={'document:Z'}
  allowed={true}
  skipSetup={true}
  allowedLanguages={[
    SupportedLanguage.JS_SDK,
    SupportedLanguage.GO_SDK,
    SupportedLanguage.DOTNET_SDK,
    SupportedLanguage.PYTHON_SDK,
    SupportedLanguage.JAVA_SDK,
    SupportedLanguage.CLI,
    SupportedLanguage.CURL,
  ]}
/>

The result's `allowed` field will return `true` if the relationship exists and `false` if the relationship does not exist.

##### 03. Calling Batch Check API

If you want to check multiple user-object-relationship combinations in a single request, you can use the [Batch Check](https://github.com/openfga/openfga.dev/blob/main//api/service#Relationship%20Queries/BatchCheck) API endpoint. Batching authorization checks together in a single request significantly reduces overall network latency.    

:::note
The BatchCheck endpoint is currently only supported by the JS SDK (>=[v0.8.0](https://github.com/openfga/js-sdk/releases/tag/v0.8.0) and the Python SDK (>=[v0.9.0](https://github.com/openfga/python-sdk/releases/tag/v0.9.0)). Support in the other SDKs is being worked on.

In the SDKs that don't support the server-side `BatchCheck`, the `BatchCheck` method performs client-side batch checks by making multiple check requests with limited parallelization, in SDK versions that do support the server-side `BatchCheck`, the existing method has been renamed to `ClientBatchCheck`.

Refer to the README for each SDK for more information. Refer to the release notes of the relevant SDK version for more information on how to migrate from client-side to the server-side `BatchCheck`.
:::

The BatchCheck endpoint requires a `correlation_id` parameter for each check. The `correlation_id` is used to "correlate" the check responses with the checks sent in the request, since `tuple_keys` and `contextual_tuples` are not returned in the response on purpose to reduce data transfer to improve network latency. A `correlation_id` can be composed of any string of alphanumeric characters or dashes between 1-36 characters in length.
This means you can use:
- simple iterating integers `1,2,3,etc`
- UUID `e5fe049b-f252-40b3-b795-fe485d588279`
- ULID `01JBMD9YG0XH3B4GVA8A9D2PSN`
- or some other unique string

Each `correlation_id` within a request must be unique.

:::note
If you are using one of our SDKs:
* the `correlation_id` is inserted for you by default and automatically correlates the `allowed` response with the proper `tuple_key`
* if you pass in more checks than the server supports in a single call (default `50`, configurable on the server), the SDK will automatically split and batch the `BatchCheck` requests for you, how it does this can be configured using the `maxBatchSize` and `maxParallelRequests` options in the SDK.
:::

To check whether user `user:anne` has multiple relationships `writer` and `reader` with object `document:Z`

<BatchCheckRequestViewer
  checks={[
    {
      user: 'user:anne',
      relation: 'writer',
      object: 'document:Z',
      correlation_id: '886224f6-04ae-4b13-bd8e-559c7d3754e1',
      allowed: false
    },
    {
      user: 'user:anne',
      relation: 'reader',
      object: 'document:Z',
      correlation_id: 'da452239-a4e0-4791-b5d1-fb3d451ac078',
      allowed: true
    }
  ]}
  skipSetup={true}
/>

The result will include an `allowed` field for each authorization check that will return `true` if the relationship exists and `false` if the relationship does not exist.

###### Configuring Batch Check
BatchCheck has two available configuration options:

1. Limit the number of checks allowed in a single BatchCheck request.
    * Environment variable: `OPENFGA_MAX_CHECKS_PER_BATCH_CHECK`
    * Command line flag: `--max-checks-per-batch-check`
    * If more items are received in a single request than allowed by this limit, the API will return an error.

2. Limit the number of Checks which can be resolved concurrently
    * Environment variable: `OPENFGA_MAX_CONCURRENT_CHECKS_PER_BATCH_CHECK`
    * Command line flag: `--max-concurrent-checks-per-batch-check`

#### Related Sections

<RelatedSection
  description="Take a look at the following section for more on how to perform authorization checks in your system"
  relatedLinks={[
    {
      title: '{ProductName} Check API',
      description: 'Read the Check API documentation and see how it works.',
      link: '/api/service#Relationship%20Queries/Check',
    },
    {
      title: '{ProductName} Batch Check API',
      description: 'Read the Batch Check API documentation and see how it works.',
      link: '/api/service#Relationship%20Queries/BatchCheck',
    },
  ]}
/>

<!-- End of openfga/openfga.dev/docs/content/getting-started/perform-check.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/perform-list-objects.mdx -->

---
title: Perform a List Objects call
sidebar_position: 4
slug: /getting-started/perform-list-objects
description: List all objects a user is authorized to perform a specified action for a given resource type
---

import {
  SupportedLanguage,
  languageLabelMap,
  ListObjectsRequestViewer,
  DocumentationNotice,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  SdkSetupHeader,
  SdkSetupPrerequisite,
} from '@components/Docs';
import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

### Perform a list objects call

<DocumentationNotice />

This section describes how to perform a <ProductConcept section="what-is-a-list-objects-request" linkName="list objects" /> request. The List Objects API allows you to retrieve all <ProductConcept section="what-is-an-object" linkName="objects" /> of a specified <ProductConcept section="what-is-a-type" linkName="type" /> that a <ProductConcept section="what-is-a-user" linkName="user" /> has a given <ProductConcept section="what-is-a-relationship" linkName="relationship" /> with. This can be used in scenarios like displaying all documents a user can read or listing resources a user can manage.

#### Before you start

<Tabs groupId="languages">

<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.DOTNET_SDK} label={languageLabelMap.get(SupportedLanguage.DOTNET_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.PYTHON_SDK} label={languageLabelMap.get(SupportedLanguage.PYTHON_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.JAVA_SDK} label={languageLabelMap.get(SupportedLanguage.JAVA_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.CLI} label={languageLabelMap.get(SupportedLanguage.CLI)}>

1. <SdkSetupPrerequisite />
2. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx).
3. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.CURL} label={languageLabelMap.get(SupportedLanguage.CURL)}>

1. <SdkSetupPrerequisite />
2. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
3. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>
</Tabs>

#### Step by step

Consider the following model which includes a `user` that can have a `reader` relationship with a `document`:

```dsl.openfga
model
  schema 1.1

type user

type document
  relations
    define reader: [user]

```

Assume that you want to list all objects of type document that  user `anne` has `reader` relationship with:

##### 01. Configure the <ProductName format={ProductNameFormat.ShortForm}/> API client

Before calling the check API, you will need to configure the API client.

<Tabs groupId="languages">
<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.JS_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.GO_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.DOTNET_SDK} label={languageLabelMap.get(SupportedLanguage.DOTNET_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.DOTNET_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.PYTHON_SDK} label={languageLabelMap.get(SupportedLanguage.PYTHON_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.PYTHON_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.JAVA_SDK} label={languageLabelMap.get(SupportedLanguage.JAVA_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.JAVA_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.CLI} label={languageLabelMap.get(SupportedLanguage.CLI)}>

<SdkSetupHeader lang={SupportedLanguage.CLI} />

</TabItem>
<TabItem value={SupportedLanguage.CURL} label={languageLabelMap.get(SupportedLanguage.CURL)}>

To obtain the [access token](https://auth0.com/docs/get-started/authentication-and-authorization-flow/call-your-api-using-the-client-credentials-flow):

<SdkSetupHeader lang={SupportedLanguage.CURL} />

</TabItem>
</Tabs>

##### 02. Calling list objects API

To return all documents that user `user:anne` has relationship `reader` with:

<ListObjectsRequestViewer
  authorizationModelId="01HVMMBCMGZNT3SED4Z17ECXCA"
  objectType="document"
  relation="reader"
  user="user:anne"
  expectedResults={['document:otherdoc', 'document:planning']}
  skipSetup={true}
  allowedLanguages={[
    SupportedLanguage.JS_SDK,
    SupportedLanguage.GO_SDK,
    SupportedLanguage.DOTNET_SDK,
    SupportedLanguage.PYTHON_SDK,
    SupportedLanguage.JAVA_SDK,
    SupportedLanguage.CLI,
    SupportedLanguage.CURL,
  ]}
/>

The result `document:otherdoc` and `document:planning` are the document objects that `user:anne` has `reader` relationship with.

:::caution Warning
The performance characteristics of the ListObjects endpoint vary drastically depending on the model complexity, number of tuples, and the relations it needs to evaluate. Relations with 'and' or 'but not' are more expensive to evaluate than relations with 'or'.
:::

#### Related Sections

<RelatedSection
  description="Take a look at the following section for more on how to perform authorization checks in your system"
  relatedLinks={[
    {
      title: '{ProductName} List Objects API',
      description: 'Read the List Objects API documentation and see how it works.',
      link: '/api/service#Relationship%20Queries/ListObjects',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/getting-started/perform-list-objects.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/perform-list-users.mdx -->

---
title: Perform a List Users call
sidebar_position: 4
slug: /getting-started/perform-list-users
description: List all users that have a certain relation with a particular object
---

import {
  SupportedLanguage,
  languageLabelMap,
  ListUsersRequestViewer,
  DocumentationNotice,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  SdkSetupHeader,
  SdkSetupPrerequisite,
} from '@components/Docs';
import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

### Perform a List Users call

<DocumentationNotice />

This section will illustrate how to perform a <ProductConcept section="what-is-a-list-users-request" linkName="list users" /> request. The List Users call allows you to retrieve a list of <ProductConcept section="what-is-a-user" linkName="users" /> that have a specific <ProductConcept section="what-is-a-relationship" linkName="relationship" /> with a given <ProductConcept section="what-is-an-object" linkName="object" />.  This can be used in scenarios such as retrieving users who have access to a resource or managing members in a group. 


#### Before You Start

<Tabs groupId="languages">

<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.DOTNET_SDK} label={languageLabelMap.get(SupportedLanguage.DOTNET_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.PYTHON_SDK} label={languageLabelMap.get(SupportedLanguage.PYTHON_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.JAVA_SDK} label={languageLabelMap.get(SupportedLanguage.JAVA_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.CLI} label={languageLabelMap.get(SupportedLanguage.CLI)}>

1. <SdkSetupPrerequisite />
2. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx).
3. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.CURL} label={languageLabelMap.get(SupportedLanguage.CURL)}>

1. <SdkSetupPrerequisite />
2. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx) and [updated the _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx).
3. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>
</Tabs>

#### Step by step

Consider the following model which includes a `user` that can have a `reader` relationship with a `document`:

```dsl.openfga
model
  schema 1.1

type user

type document
  relations
    define reader: [user]

```

Assume that you want to list all users of type `user` that have a `reader` relationship with `document:planning`:

##### 01. Configure the <ProductName format={ProductNameFormat.ShortForm}/> API client

Before calling the List Users API, you will need to configure the API client.

<Tabs groupId="languages">
<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.JS_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.GO_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.DOTNET_SDK} label={languageLabelMap.get(SupportedLanguage.DOTNET_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.DOTNET_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.PYTHON_SDK} label={languageLabelMap.get(SupportedLanguage.PYTHON_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.PYTHON_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.JAVA_SDK} label={languageLabelMap.get(SupportedLanguage.JAVA_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.JAVA_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.CLI} label={languageLabelMap.get(SupportedLanguage.CLI)}>

<SdkSetupHeader lang={SupportedLanguage.CLI} />

</TabItem>
<TabItem value={SupportedLanguage.CURL} label={languageLabelMap.get(SupportedLanguage.CURL)}>

To obtain the [access token](https://auth0.com/docs/get-started/authentication-and-authorization-flow/call-your-api-using-the-client-credentials-flow):

<SdkSetupHeader lang={SupportedLanguage.CURL} />

</TabItem>
</Tabs>

##### 02. Calling List Users API

To return all users of type `user` that have have the `reader` relationship with `document:planning`:

<ListUsersRequestViewer
  authorizationModelId="01HVMMBCMGZNT3SED4Z17ECXCA"
  objectType="document"
  objectId="planning"
  relation="reader"
  userFilterType="user"
  expectedResults={{
    users: [{ object: { type: 'user', id: 'anne' } }, { object: { type: 'user', id: 'beth' } }],
  }}
  skipSetup={true}
  allowedLanguages={[
    SupportedLanguage.JS_SDK,
    SupportedLanguage.GO_SDK,
    SupportedLanguage.DOTNET_SDK,
    SupportedLanguage.PYTHON_SDK,
    SupportedLanguage.JAVA_SDK,
    SupportedLanguage.CLI,
    SupportedLanguage.CURL,
  ]}
/>

The result `user:anne` and `user:beth` are the `user` objects that have the `reader` relationship with `document:planning`.

:::caution Warning
The performance characteristics of the List Users endpoint vary drastically depending on the model complexity, number of tuples, and the relations it needs to evaluate. Relations with 'and' or 'but not' are particularly expensive to evaluate.
:::

#### Usersets

In the above example, only specific subjects of the `user` type were returned. However, groups of users, known as [usersets](https://github.com/openfga/openfga.dev/blob/main/../modeling/building-blocks/usersets.mdx), can also be returned from the List Users API. This is done by specifying a `relation` field in the `user_filters` request object. Usersets will only expand to the underlying subjects if that `type` is specified as the user filter object.

Below is an example where usersets can be returned:

```dsl.openfga
model
  schema 1.1

type user

type group
  relations
    define member: [ user ]

type document
  relations
    define viewer: [ group#member ]
```

With the tuples:

| user                     | relation | object                   |
| ------------------------ | -------- | ------------------------ |
| group:engineering#member | viewer   | document:1               |
| group:product#member     | viewer   | document:1               |
| user:will                | member   | group:engineering        |

Then calling the List Users API for `document:1` with relation `viewer` of type `group#member` will yield the below response. Note that the `user:will` is not returned, despite being a member of `group:engineering#member` because the `user_filters` does not target the `user` type.

<ListUsersRequestViewer
  authorizationModelId="01HXHK5D1Z6SCG1SV7M3BVZVCV"
  objectType="document"
  objectId="1"
  relation="viewer"
  userFilterType="group"
  userFilterRelation="member"
  expectedResults={{
    users: [
      {
        userset: {
          id: 'engineering',
          relation: 'member',
          type: 'group',
        },
      },
      {
        userset: {
          id: 'product',
          relation: 'member',
          type: 'group',
        },
      },
    ],
  }}
  skipSetup={true}
  allowedLanguages={[
    SupportedLanguage.JS_SDK,
    SupportedLanguage.GO_SDK,
    SupportedLanguage.DOTNET_SDK,
    SupportedLanguage.PYTHON_SDK,
    SupportedLanguage.JAVA_SDK,
    SupportedLanguage.CLI,
    SupportedLanguage.CURL,
  ]}
/>

#### Type-bound public access

The List Users API supports tuples expressing public access via the wildcard syntax (e.g. `user:*`). Wildcard tuples that satisfy the query criteria will be returned with the `wildcard` root object property that will specify the type. A typed-bound public access result indicates that the object has a public relation but it doesn't necessarily indicate that all users of that type have that relation, it is possible that exclusions via the `but not` syntax exists. The API will not expand wildcard results further to any ID'd user object. Further, specific users that have been granted access will be returned in addition to any public access for that user's type.

:::caution
A List Users response with a type-bound public access result (e.g. `user:*`) doesn't necessarily indicate that all users of that type have access, it is possible that exclusions exist. It is recommended to [perform a Check](https://github.com/openfga/openfga.dev/blob/main/./perform-check.mdx) on specific users to ensure they have access to the target object.
:::

Example response with type-bound public access:

```json
{
  "users": [
    {
      "wildcard": {
        "type": "user"
      }
    },
    {
      "object": {
        "type": "user",
        "id": "anne"
      }
    }
  ]
}
```

#### Related Sections

<RelatedSection
  description="Take a look at the following section for more on how to perform list users in your system"
  relatedLinks={[
    {
      title: '{ProductName} List Users API',
      description: 'Read the List Users API documentation and see how it works.',
      link: '/api/service#Relationship%20Queries/ListUsers',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/getting-started/perform-list-users.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/setup-sdk-client.mdx -->

---
title: Setup SDK Client for Store
description: Setting up an OpenFGA SDK client
slug: /getting-started/setup-sdk-client
---

import {
    SupportedLanguage,
    languageLabelMap,
    DocumentationNotice,
} from '@components/Docs';
import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

### Setup SDK Client for Store

<DocumentationNotice />

This article explains how to build an OpenFGA client by using the SDKs.

The first step is to ensure that you have created a store by following [these steps](https://github.com/openfga/openfga.dev/blob/main/./create-store.mdx).

Next, depending on the authentication scheme you want to use, there are different ways to build the client.

#### Using No Authentication

This is a simple setup but it is not recommended for production use.

<Tabs groupId="languages">
<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

```javascript
const { OpenFgaClient } = require('@openfga/sdk'); // OR import { OpenFgaClient } from '@openfga/sdk';

const openFga = new OpenFgaClient({
  apiUrl: process.env.FGA_API_URL, // required, e.g. https://api.fga.example
  storeId: process.env.FGA_STORE_ID,
  authorizationModelId: process.env.FGA_MODEL_ID, // Optional, can be overridden per request
});
```

</TabItem>
<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

```go
import (
    "os"

    . "github.com/openfga/go-sdk/client"
)

func main() {
    fgaClient, err := NewSdkClient(&ClientConfiguration{
        ApiUrl:               os.Getenv("FGA_API_URL"), // required, e.g. https://api.fga.example
        StoreId:              os.Getenv("FGA_STORE_ID"), // optional, not needed for \`CreateStore\` and \`ListStores\`, required before calling for all other methods
        AuthorizationModelId: os.Getenv("FGA_MODEL_ID"),  // Optional, can be overridden per request
    })

    if err != nil {
        // .. Handle error
    }
}
```

</TabItem>
<TabItem value={SupportedLanguage.DOTNET_SDK} label={languageLabelMap.get(SupportedLanguage.DOTNET_SDK)}>

```dotnet
using OpenFga.Sdk.Client;
using OpenFga.Sdk.Client.Model;
using OpenFga.Sdk.Model;
using Environment = System.Environment;

namespace ExampleApp;

class MyProgram {
    static async Task Main() {
        var configuration = new ClientConfiguration() {
            ApiUrl = Environment.GetEnvironmentVariable("FGA_API_URL") ?? "http://localhost:8080", // required, e.g. https://api.fga.example
            StoreId = Environment.GetEnvironmentVariable("FGA_STORE_ID"), // optional, not needed for \`CreateStore\` and \`ListStores\`, required before calling for all other methods
            AuthorizationModelId = Environment.GetEnvironmentVariable("FGA_MODEL_ID"), // optional, can be overridden per request
        };
        var fgaClient = new OpenFgaClient(configuration);
    }
}
```

</TabItem>
<TabItem value={SupportedLanguage.PYTHON_SDK} label={languageLabelMap.get(SupportedLanguage.PYTHON_SDK)}>

```python
import asyncio
import os
import openfga_sdk
from openfga_sdk.client import OpenFgaClient

async def main():
    configuration = openfga_sdk.ClientConfiguration(
        api_url = os.environ.get('FGA_API_URL'), # required, e.g. https://api.fga.example
        store_id = os.environ.get('FGA_STORE_ID'), # optional, not needed for \`CreateStore\` and \`ListStores\`, required before calling for all other methods
        authorization_model_id = os.environ.get('FGA_MODEL_ID'), # Optional, can be overridden per request
    )

    async with OpenFgaClient(configuration) as fga_client:
        api_response = await fga_client.read_authorization_models() # call requests
        await fga_client.close() # close when done

asyncio.run(main())
```

</TabItem>

<TabItem value={SupportedLanguage.JAVA_SDK} label={languageLabelMap.get(SupportedLanguage.JAVA_SDK)}>

```java
import dev.openfga.sdk.api.client.OpenFgaClient;
import dev.openfga.sdk.api.configuration.ClientConfiguration;

public class Example {
    public static void main(String[] args) {
        var config = new ClientConfiguration()
                .apiUrl(System.getenv("FGA_API_URL")) // If not specified, will default to "https://localhost:8080"
                .storeId(System.getenv("FGA_STORE_ID")) // Not required when calling createStore() or listStores()
                .authorizationModelId(System.getenv("FGA_AUTHORIZATION_MODEL_ID")); // Optional, can be overridden per request

        var fgaClient = new OpenFgaClient(config);
    }
}
```

</TabItem>

<TabItem value={SupportedLanguage.CLI} label={languageLabelMap.get(SupportedLanguage.CLI)}>

```shell
export FGA_API_URL=https://api.fga.example # optional. Defaults to http://localhost:8080
export FGA_STORE_ID=YOUR_STORE_ID # required for all calls except \`store create\`, \`store list\` and \`model validate\`
export FGA_MODEL_ID=YOUR_MODEL_ID # optional, can be overridden per request, latest is used if this is empty
```

</TabItem>

</Tabs>

#### Using shared key authentication

If you want to use shared key authentication, you need to generate a random string that will work as secret and set that key when building your OpenFGA server. Then, when building the client, set it as environment variable `FGA_API_TOKEN`.

:::caution Warning
If you are going to use this setup in production, you should enable TLS in your OpenFGA server. Please see the [Running OpenFGA in Production](https://github.com/openfga/openfga.dev/blob/main/../best-practices/running-in-production.mdx).
:::


<Tabs groupId="languages">
<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

```javascript
const { CredentialsMethod, OpenFgaClient } = require('@openfga/sdk'); // OR import { CredentialsMethod, OpenFgaClient } from '@openfga/sdk';

const openFga = new OpenFgaClient({
    apiUrl: process.env.FGA_API_URL, // required, e.g. https://api.fga.example
    storeId: process.env.FGA_STORE_ID, // optional, not needed for \`CreateStore\` and \`ListStores\`, required before calling for all other methods
    authorizationModelId: process.env.FGA_MODEL_ID, // optional, can be overridden per request
    credentials: {
        method: CredentialsMethod.ApiToken,
        config: {
            token: process.env.$FGA_API_TOKEN,
        },
    }
});
```

</TabItem>
<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

```go
import (
    "os"

    . "github.com/openfga/go-sdk/client"
    "github.com/openfga/go-sdk/credentials"
)

func main() {
    fgaClient, err := NewSdkClient(&ClientConfiguration{
        ApiUrl:               os.Getenv("FGA_API_URL"), // required, e.g. https://api.fga.example
        StoreId:              os.Getenv("FGA_STORE_ID"),   // optional, not needed for \`CreateStore\` and \`ListStores\`, required before calling for all other methods
        AuthorizationModelId: os.Getenv("FGA_MODEL_ID"),   // optional, can be overridden per request
        Credentials: &credentials.Credentials{
            Method: credentials.CredentialsMethodApiToken,
            Config: &credentials.Config{
                ApiToken: os.Getenv("OPENFGA_API_TOKEN"), // will be passed as the "Authorization: Bearer ${ApiToken}" request header
            },
        },
    })

    if err != nil {
        // .. Handle error
    }
}
```

</TabItem>
<TabItem value={SupportedLanguage.DOTNET_SDK} label={languageLabelMap.get(SupportedLanguage.DOTNET_SDK)}>

```dotnet
using OpenFga.Sdk.Client;
using OpenFga.Sdk.Configuration;
using Environment = System.Environment;

namespace ExampleApp;

class MyProgram {
    static async Task Main() {
        var configuration = new ClientConfiguration() {
            ApiUrl = Environment.GetEnvironmentVariable("FGA_API_URL") ?? "http://localhost:8080", // required, e.g. https://api.fga.example
            StoreId = Environment.GetEnvironmentVariable("FGA_STORE_ID"), // optional, not needed for \`CreateStore\` and \`ListStores\`, required before calling for all other methods
            AuthorizationModelId = Environment.GetEnvironmentVariable("FGA_MODEL_ID"), // optional, can be overridden per request
            Credentials = new Credentials() {
                Method = CredentialsMethod.ApiToken,
                Config = new CredentialsConfig() {
                    ApiToken = Environment.GetEnvironmentVariable("FGA_API_TOKEN")
                },
            },
        };
        var fgaClient = new OpenFgaClient(configuration);
    }
}
```

</TabItem>
<TabItem value={SupportedLanguage.PYTHON_SDK} label={languageLabelMap.get(SupportedLanguage.PYTHON_SDK)}>

```python
import asyncio
import os
import openfga_sdk
from openfga_sdk.client import OpenFgaClient
from openfga_sdk.credentials import Credentials, CredentialConfiguration

async def main():

    credentials = Credentials(
        method='api_token',
        configuration=CredentialConfiguration(
            api_token=os.environ.get('FGA_API_TOKEN')
        )
    )
    configuration = openfga_sdk.ClientConfiguration(
        api_url = os.environ.get('FGA_API_URL'), # required, e.g. https://api.fga.example
        store_id = os.environ.get('FGA_STORE_ID'), # optional, not needed for \`CreateStore\` and \`ListStores\`, required before calling for all other methods
        authorization_model_id = os.environ.get('FGA_MODEL_ID'), # Optional, can be overridden per request
        credentials = credentials,
    )

    async with OpenFgaClient(configuration) as fga_client:
        api_response = await fga_client.read_authorization_models() # call requests
        await fga_client.close() # close when done

asyncio.run(main())
```

</TabItem>
<TabItem value={SupportedLanguage.JAVA_SDK} label={languageLabelMap.get(SupportedLanguage.JAVA_SDK)}>

```java
import dev.openfga.sdk.api.client.OpenFgaClient;
import dev.openfga.sdk.api.configuration.ApiToken;
import dev.openfga.sdk.api.configuration.ClientConfiguration;
import dev.openfga.sdk.api.configuration.Credentials;

public class Example {
    public static void main(String[] args) {
        var config = new ClientConfiguration()
                .apiUrl(System.getenv("FGA_API_URL")) // If not specified, will default to "https://localhost:8080"
                .storeId(System.getenv("FGA_STORE_ID")) // Not required when calling createStore() or listStores()
                .authorizationModelId(System.getenv("FGA_AUTHORIZATION_MODEL_ID")) // Optional, can be overridden per request
                .credentials(new Credentials(
                    new ApiToken(System.getenv("FGA_API_TOKEN")) // will be passed as the "Authorization: Bearer ${ApiToken}" request header
                ));

        var fgaClient = new OpenFgaClient(config);
    }
}
```

</TabItem>
<TabItem value={SupportedLanguage.CLI} label={languageLabelMap.get(SupportedLanguage.CLI)}>

```shell
export FGA_API_URL=https://api.fga.example # optional. Defaults to http://localhost:8080
export FGA_STORE_ID=YOUR_STORE_ID # required for all calls except \`store create\`, \`store list\` and \`model validate\`
export FGA_MODEL_ID=YOUR_MODEL_ID # optional, can be overridden per request, latest is used if this is empty
export FGA_API_TOKEN=YOUR_API_TOKEN
```

</TabItem>
</Tabs>

#### Using client credentials flow

:::info Note
The OpenFGA server does not support the client credentials flow, however if you or your OpenFGA provider have implemented a client credentials wrapper on top, follow the instructions here to have the OpenFGA client handle the token exchange for you.
:::

<Tabs groupId="languages">
<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

```javascript
const { CredentialsMethod, OpenFgaClient } = require('@openfga/sdk'); // OR import { CredentialsMethod, OpenFgaClient } from '@openfga/sdk';

const openFga = new OpenFgaClient({
    apiUrl: process.env.FGA_API_URL, // required, e.g. https://api.fga.example
    storeId: process.env.FGA_STORE_ID, // optional, not needed for \`CreateStore\` and \`ListStores\`, required before calling for all other methods
    authorizationModelId: process.env.FGA_MODEL_ID, // optional, can be overridden per request
    credentials: {
        method: CredentialsMethod.ClientCredentials,
        config: {
          apiTokenIssuer: process.env.FGA_API_TOKEN_ISSUER,
          apiAudience: process.env.FGA_API_AUDIENCE,
          clientId: process.env.FGA_CLIENT_ID,
          clientSecret: process.env.FGA_CLIENT_SECRET,
        },
    }
});
```

</TabItem>
<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

```go
import (
    "os"

    . "github.com/openfga/go-sdk/client"
    "github.com/openfga/go-sdk/credentials"
)

func main() {
    fgaClient, err := NewSdkClient(&ClientConfiguration{
        ApiUrl:               os.Getenv("FGA_API_URL"), // required, e.g. https://api.fga.example
        StoreId:              os.Getenv("FGA_STORE_ID"),   // optional, not needed for \`CreateStore\` and \`ListStores\`, required before calling for all other methods
        AuthorizationModelId: os.Getenv("FGA_MODEL_ID"),   // optional, can be overridden per request
        Credentials: &credentials.Credentials{
            Method: credentials.CredentialsMethodClientCredentials,
            Config: &credentials.Config{
                ClientCredentialsClientId:       os.Getenv("FGA_CLIENT_ID"),
                ClientCredentialsClientSecret:   os.Getenv("FGA_CLIENT_SECRET"),
                ClientCredentialsApiAudience:    os.Getenv("FGA_API_AUDIENCE"),
                ClientCredentialsApiTokenIssuer: os.Getenv("FGA_API_TOKEN_ISSUER"),
            },
        },
    })

    if err != nil {
        // .. Handle error
    }
}
```

</TabItem>
<TabItem value={SupportedLanguage.DOTNET_SDK} label={languageLabelMap.get(SupportedLanguage.DOTNET_SDK)}>

```dotnet
using OpenFga.Sdk.Client;
using OpenFga.Sdk.Configuration;
using Environment = System.Environment;

namespace ExampleApp;

class MyProgram {
    static async Task Main() {
        var configuration = new ClientConfiguration() {
            ApiUrl = Environment.GetEnvironmentVariable("FGA_API_URL") ?? "http://localhost:8080", // required, e.g. https://api.fga.example
            StoreId = Environment.GetEnvironmentVariable("FGA_STORE_ID"), // optional, not needed for \`CreateStore\` and \`ListStores\`, required before calling for all other methods
            AuthorizationModelId = Environment.GetEnvironmentVariable("FGA_MODEL_ID"), // optional, can be overridden per request
            Credentials = new Credentials() {
                Method = CredentialsMethod.ClientCredentials,
                Config = new CredentialsConfig() {
                    ApiTokenIssuer = Environment.GetEnvironmentVariable("FGA_API_TOKEN_ISSUER"),
                    ApiAudience = Environment.GetEnvironmentVariable("FGA_API_AUDIENCE"),
                    ClientId = Environment.GetEnvironmentVariable("FGA_CLIENT_ID"),
                    ClientSecret = Environment.GetEnvironmentVariable("FGA_CLIENT_SECRET"),
                }
            }
        };
        var fgaClient = new OpenFgaClient(configuration);
    }
}
```

</TabItem>
<TabItem value={SupportedLanguage.PYTHON_SDK} label={languageLabelMap.get(SupportedLanguage.PYTHON_SDK)}>

```python
import asyncio
import os
import openfga_sdk
from openfga_sdk.client import OpenFgaClient
from openfga_sdk.credentials import Credentials, CredentialConfiguration

async def main():

    credentials = Credentials(
        method='client_credentials',
        configuration=CredentialConfiguration(
            api_issuer= os.environ.get('FGA_API_TOKEN_ISSUER'),
            api_audience= os.environ.get('FGA_API_AUDIENCE'),
            client_id= os.environ.get('FGA_CLIENT_ID'),
            client_secret= os.environ.get('FGA_CLIENT_SECRET'),
        )
    )
    configuration = openfga_sdk.ClientConfiguration(
        api_url = os.environ.get('FGA_API_URL'), # required, e.g. https://api.fga.example
        store_id = os.environ.get('FGA_STORE_ID'), # optional, not needed for \`CreateStore\` and \`ListStores\`, required before calling for all other methods
        authorization_model_id = os.environ.get('FGA_MODEL_ID'), # Optional, can be overridden per request
        credentials = credentials,
    )

    async with OpenFgaClient(configuration) as fga_client:
        api_response = await fga_client.read_authorization_models() # call requests
        await fga_client.close() # close when done

asyncio.run(main())
```

</TabItem>
<TabItem value={SupportedLanguage.JAVA_SDK} label={languageLabelMap.get(SupportedLanguage.JAVA_SDK)}>

```java
import dev.openfga.sdk.api.client.OpenFgaClient;
import dev.openfga.sdk.api.configuration.ClientConfiguration;
import dev.openfga.sdk.api.configuration.ClientCredentials;
import dev.openfga.sdk.api.configuration.Credentials;

public class Example {
    public static void main(String[] args) {
        var config = new ClientConfiguration()
                .apiUrl(System.getenv("FGA_API_URL")) // If not specified, will default to "https://localhost:8080"
                .storeId(System.getenv("FGA_STORE_ID")) // Not required when calling createStore() or listStores()
                .authorizationModelId(System.getenv("FGA_AUTHORIZATION_MODEL_ID")) // Optional, can be overridden per request
                .credentials(new Credentials(
                    new ClientCredentials()
                            .apiTokenIssuer(System.getenv("FGA_API_TOKEN_ISSUER"))
                            .apiAudience(System.getenv("FGA_API_AUDIENCE"))
                            .clientId(System.getenv("FGA_CLIENT_ID"))
                            .clientSecret(System.getenv("FGA_CLIENT_SECRET"))
                ));

        var fgaClient = new OpenFgaClient(config);
    }
}
```

</TabItem>
<TabItem value={SupportedLanguage.CLI} label={languageLabelMap.get(SupportedLanguage.CLI)}>

```shell
export FGA_API_URL=https://api.fga.example # optional. Defaults to http://localhost:8080
export FGA_STORE_ID=YOUR_STORE_ID # required for all calls except \`store create\`, \`store list\` and \`model validate\`
export FGA_MODEL_ID=YOUR_MODEL_ID # optional, can be overridden per request, latest is used if this is empty
export FGA_API_TOKEN_ISSUER=YOUR_API_TOKEN_ISSUER
export FGA_API_AUDIENCE=YOUR_API_AUDIENCE
export FGA_CLIENT_ID=YOUR_CLIENT_ID
export FGA_CLIENT_SECRET=YOUR_CLIENT_SECRET
```

</TabItem>
</Tabs>


<!-- End of openfga/openfga.dev/docs/content/getting-started/setup-sdk-client.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/tuples-api-best-practices.mdx -->

---
title: Managing Tuples and Invoking API Best Practices
description: Best Practices of Managing Tuples and Invoking APIs
sidebar_position: 8
slug: /getting-started/tuples-api-best-practices
---

import {
  DocumentationNotice,
  ProductName,
  ProductNameFormat,
  RelatedSection,
} from "@components/Docs";

### Best Practices of Managing Tuples and Invoking APIs

<DocumentationNotice />

The following list outlines some guidelines and best practices for using <ProductName format={ProductNameFormat.ShortForm}/>:

- Do not store Personal Identifiable Information in tuples
- Always specify authorization model ID whenever possible

#### Do Not Store Personal Identifiable Information in Tuples

You can use any string for user and object identifiers, however you should not input or assign identifiers that include Personal Data or any other sensitive data, such as data that may be restricted under regulatory requirements.

:::caution Note
The documentation and samples uses first names and simple ids to illustrate easy-to-follow examples.
:::

#### Always specify authorization model ID whenever possible

It is strongly recommended that authorization model ID be specified in your Relationship Queries (such as [Check](https://github.com/openfga/openfga.dev/blob/main/./perform-check.mdx) and [ListObjects](https://github.com/openfga/openfga.dev/blob/main/../interacting/relationship-queries.mdx#listobjects)) and Relationship Commands (such as [Write](https://github.com/openfga/openfga.dev/blob/main/./update-tuples.mdx)).

Specifying authorization model ID in API calls have the following advantages:
1. Better performance as <ProductName format={ProductNameFormat.ShortForm}/> will not need to perform a database query to get the latest authorization model ID.
2. Allows consistent behavior in your production system until you are ready to switch to the new model.

#### Related Sections

<RelatedSection
  description="Check the following sections for more on recommendation for managing relations and model in production environment."
  relatedLinks={[
    {
      title: 'Migrating Relations',
      description: 'Learn how to migrate relations in a production environment',
      link: '../modeling/migrating/migrating-relations',
      id: '../modeling/migrating/migrating-relations',
    }
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/getting-started/tuples-api-best-practices.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/getting-started/update-tuples.mdx -->

---
title: Update Relationship Tuples
sidebar_position: 3
slug: /getting-started/update-tuples
description: Updating system state by writing and deleting relationship tuples
---

import {
  DocumentationNotice,
  RelatedSection,
  SdkSetupHeader,
  SupportedLanguage,
  languageLabelMap,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  WriteRequestViewer,
  SdkSetupPrerequisite,
} from '@components/Docs';
import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

### Update Relationship Tuples

<DocumentationNotice />

This section will illustrate how to update _<ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuples" />_.

#### Before you start

<Tabs groupId="languages">
<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>
<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>
<TabItem value={SupportedLanguage.DOTNET_SDK} label={languageLabelMap.get(SupportedLanguage.DOTNET_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>
<TabItem value={SupportedLanguage.PYTHON_SDK} label={languageLabelMap.get(SupportedLanguage.PYTHON_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>
<TabItem value={SupportedLanguage.JAVA_SDK} label={languageLabelMap.get(SupportedLanguage.JAVA_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/./install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>
<TabItem value={SupportedLanguage.CLI} label={languageLabelMap.get(SupportedLanguage.CLI)}>

1. <SdkSetupPrerequisite />
2. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx).
3. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>
<TabItem value={SupportedLanguage.CURL} label={languageLabelMap.get(SupportedLanguage.CURL)}>

1. <SdkSetupPrerequisite />
2. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/./configure-model.mdx).
3. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>
</Tabs>

#### Step by step

Assume that you want to add user `user:anne` to have relationship `reader` with object `document:Z`

```json
{
  user: 'user:anne',
  relation: 'reader',
  object: 'document:Z',
}
```

##### 01. Configure the <ProductName format={ProductNameFormat.ShortForm}/> API client

Before calling the write API, you will need to configure the API client.

<Tabs groupId="languages">
<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.JS_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.GO_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.DOTNET_SDK} label={languageLabelMap.get(SupportedLanguage.DOTNET_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.DOTNET_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.PYTHON_SDK} label={languageLabelMap.get(SupportedLanguage.PYTHON_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.PYTHON_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.JAVA_SDK} label={languageLabelMap.get(SupportedLanguage.JAVA_SDK)}>

<SdkSetupHeader lang={SupportedLanguage.JAVA_SDK} />

</TabItem>
<TabItem value={SupportedLanguage.CLI} label={languageLabelMap.get(SupportedLanguage.CLI)}>

<SdkSetupHeader lang={SupportedLanguage.CLI} />

</TabItem>
<TabItem value={SupportedLanguage.CURL} label={languageLabelMap.get(SupportedLanguage.CURL)}>

To obtain the [access token](https://auth0.com/docs/get-started/authentication-and-authorization-flow/client-credentials-flow/call-your-api-using-the-client-credentials-flow):

<SdkSetupHeader lang={SupportedLanguage.CURL} />

</TabItem>
</Tabs>

##### 02. Calling write API to add new relationship tuples

To add the relationship tuples, we can invoke the write API.

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:anne',
      relation: 'reader',
      object: 'document:Z',
    },
  ]}
  skipSetup={true}
  allowedLanguages={[
    SupportedLanguage.JS_SDK,
    SupportedLanguage.GO_SDK,
    SupportedLanguage.DOTNET_SDK,
    SupportedLanguage.PYTHON_SDK,
    SupportedLanguage.JAVA_SDK,
    SupportedLanguage.CLI,
    SupportedLanguage.CURL,
  ]}
/>

##### 03. Calling write API to delete relationship tuples

To delete relationship tuples, we can invoke the write API.

Assume that you want to delete user `user:anne`'s `reader` relationship with object `document:Z`

```json
{
  user: 'user:anne',
  relation: 'reader',
  object: 'document:Z',
}
```

<WriteRequestViewer
  deleteRelationshipTuples={[
    {
      user: 'user:anne',
      relation: 'reader',
      object: 'document:Z',
    },
  ]}
  skipSetup={true}
  allowedLanguages={[
    SupportedLanguage.JS_SDK,
    SupportedLanguage.GO_SDK,
    SupportedLanguage.DOTNET_SDK,
    SupportedLanguage.PYTHON_SDK,
    SupportedLanguage.JAVA_SDK,
    SupportedLanguage.CLI,
    SupportedLanguage.CURL,
  ]}
/>

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to write your authorization data"
  relatedLinks={[
    {
      title: 'Managing User Access',
      description: 'Learn about how to give a user access to a particular object.',
      link: '../interacting/managing-user-access',
      id: '../interacting/managing-user-access.mdx',
    },
    {
      title: 'Managing Group Access',
      description: 'Learn about how to give a group of users access to a particular object.',
      link: '../interacting/managing-group-access',
      id: '../interacting/managing-group-access.mdx',
    },
    {
      title: 'Transactional Writes',
      description: 'Learn about how to update multiple relations within the same API call.',
      link: '../interacting/transactional-writes',
      id: '../interacting/transactional-writes.mdx',
    },
  ]}
/>

<!-- End of openfga/openfga.dev/docs/content/getting-started/update-tuples.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/interacting/consistency.mdx -->

---
title:  Query Consistency Modes
sidebar_position: 7
slug: /interacting/consistency
description: Query Consistency Modes
---

import {
  DocumentationNotice,
  IntroductionSection,
  RelatedSection,
  ProductName,
  ProductNameFormat,
} from '@components/Docs';

### Query Consistency Modes

<DocumentationNotice />

#### Background

When querying <ProductName format={ProductNameFormat.ShortForm}/> using Read or any of the query APIs like [Check, Expand, ListObjects and ListUsers](https://github.com/openfga/openfga.dev/blob/main/./relationship-queries.mdx), you can specify a query consistency parameter that can have one of the following values:

| Name                        | Description                                                                                                   |  
|-----------------------------|---------------------------------------------------------------------------------------------------------------|
| MINIMIZE_LATENCY (default)  | <ProductName format={ProductNameFormat.ShortForm}/> will serve queries from the cache when possible           | 
| HIGHER_CONSISTENCY          | <ProductName format={ProductNameFormat.ShortForm}/> will skip the cache and query the database directly   |

If you write a tuple and you immediately make a Check on a relation affected by that tuple using `MINIMIZE_LATENCY`, the tuple change might not be taken in consideration if <ProductName format={ProductNameFormat.ShortForm}/> serves the result from the cache.

#### When to use higher consistency

When specifying `HIGHER_CONSISTENCY` you are trading off consistency for latency and system performance. Always specifying `HIGHER_CONSISTENCY` will have a significant impact in performance.

If you have a use case where higher consistency is needed, it's recommended that whenever possible, you decide in runtime the consistency level you need. If you are storing a timestamp indicating when a resource was last modified in your database, you can use that to decide the kind of request you do.

For example, if you share `document:readme` with a `user:anne` and you update a `modified_date` field in the `document` table when that happens, you can write code like the below when calling `check("user:anne", "can_view", "document:readme")` to avoid paying the price of additional latency when calling the API.

```javascript
if (date_modified + cache_time_to_live_period > Date.now()) {
    const { allowed } = await fgaClient.check(
      { user: "user:anne", relation: "can_view", object: "document:roadmap"}
    );
} else {
    const { allowed } = await fgaClient.check(
        {  user: "user:anne", relation: "can_view", object: "document:roadmap"},
        {  consistency: ConsistencyPreference.HigherConsistency }
    );
}
```

#### Cache expiration

<ProductName format={ProductNameFormat.ShortForm}/> caching is disabled by default. When caching is disabled, all queries will have strong consistency regardless of the consistency mode specified. When caching is enabled, the cache will be used for queries with `MINIMIZE_LATENCY` consistency mode.

You can use the following command line parameters to configure <ProductName format={ProductNameFormat.ShortForm}/>'s cache. To see the default value of each parameter, please run `openfga run --help`.

| Name                             | Description                                                                                                                                                                                                                                                                                                                                                                                                                                    |
|----------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| check-cache-limit                | Configures the number of items that will be kept in the in-memory cache used to resolve Check queries                                                                                                                                                                                                                                                                                                                                          |
| check-query-cache-enabled        | Enables in-memory caching of Check subproblems. For example, if you have a relation `define viewer: owner or editor`, and the query is `Check(user:anne, viewer, doc:1)`, we'll evaluate the `owner` relation and the `editor` relation and cache both results: `(user:anne, viewer, doc:1) -> allowed=true` and `(user:anne, owner, doc:1) -> allowed=true`.                                                                                  |
| check-query-cache-ttl            | Specifies the time that items will be kept in the cache of Check subproblems                                                                                                                                                                                                                                                                                                                                                                   |
| check-iterator-cache-enabled     | Enables in-memory caching of database iterators. Each iterator is the result of a database query, for example, usersets related to a specific object, or objects related to a specific user, up to a certain number of tuples per iterator                                                                                                                                                                                                     |
| check-iterator-cache-max-results | Configures the number of tuples that will be stored for each database iterator                                                                                                                                                                                                                                                                                                                                                                 |
| check-iterator-cache-ttl         | Specifies the time that items will be kept in the cache of database iterators                                                                                                                                                                                                                                                                                                                                                                  |
| cache-controller-enabled         | When enabled, cache controller will verify whether check subproblem cache and check iterator cache needs to be invalidated when there is a check or list objects API request. The invalidation determination is based on whether there are recent write or deletes for the store. This feature allows a larger check-query-cache-ttl and check-iterator-cache-ttl at the expense of additional datastore queries for recent writes and deletes.|
| cache-controller-ttl             | Specifies how frequently the cache controller checks for Writes occurring. While the cache controller result is cached, the server will not read the datastore to check whether subproblem cache and iterator cache needs to be invalidated.                                                                                                                                                                                                   |

Learn how to [configure <ProductName format={ProductNameFormat.ShortForm}/>](https://github.com/openfga/openfga.dev/blob/main/../getting-started/setup-openfga/configure-openfga.mdx).

Currently, the cache is used by Check and partially in ListObjects. It will be implemented for other query endpoints in the future.

#### Future work

The <IntroductionSection linkName="Zanzibar paper" section="what-is-zanzibar"/> has a feature called `Zookies`, which is a consistency token that is returned from Write operation. You can store that token in you resource table, and specify it in subsequent calls to query APIs. 

<ProductName format={ProductNameFormat.ShortForm}/> is considering a similar feature in future releases.

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to check, read and expand."
  relatedLinks={[
    {
      title: 'Relationship Queries',
      description: 'Comparison Between Check, Read And Expand API Calls.',
      link: './relationship-queries',
      id: './relationship-queries',
    }
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/interacting/consistency.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/interacting/contextual-tuples.mdx -->

---
title: Contextual Tuples
description: Understanding and using contextual tuples
sidebar_position: 4
slug: /interacting/contextual-tuples
---

import { ProductName, ProductNameFormat, RelatedSection } from '@components/Docs';

### Contextual Tuples

Contextual tuples are special relationship tuples that exist temporarily within a specific API request. Unlike regular relationship tuples stored in the database, contextual tuples are provided when making authorization queries and are valid only for that particular request.

#### How Contextual Tuples Work

When making requests to [Check](https://github.com/openfga/openfga.dev/blob/main//api/service#/Relationship%20Queries/Check), [BatchCheck](https://github.com/openfga/openfga.dev/blob/main//api/service#/Relationship%20Queries/BatchCheck), [ListObjects](https://github.com/openfga/openfga.dev/blob/main//api/service#/Relationship%20Queries/ListObjects), [ListUsers](https://github.com/openfga/openfga.dev/blob/main//api/service#/Relationship%20Queries/ListUsers) and [Expand](https://github.com/openfga/openfga.dev/blob/main//api/service#/Relationship%20Queries/Expand) you can include contextual tuples in the request. These tuples are treated as if they were actual stored tuples during the evaluation of that request but are not persisted to the database and will not affect other requests.

#### Common Use Cases

There are three main use cases for contextual tuples:

1. When you want to avoid synchronizing data to <ProductName format={ProductNameFormat.ShortForm}/>. This is a powerful use case, as it allows using <ProductName format={ProductNameFormat.ShortForm}/> in a hybrid mode, with some data written to the database and other data obtained before making an authorization query. A good example is using user group memberships [from an identity token issued by an identity provider](https://github.com/openfga/openfga.dev/blob/main/../modeling/token-claims-contextual-tuples.mdx). Note that while it's possible to provide all data using contextual tuples without storing any data, this approach wouldn't leverage the main benefit of the Zanzibar approach: avoiding the need to look up all data required for authorization decisions.

2. When a user has multiple relationships with the same object, and you want to specify which relationship to consider. For example, if a user belongs to multiple organizations but is logged into only one of them, you can [use contextual tuples to specify which organization to consider](https://github.com/openfga/openfga.dev/blob/main/../modeling/organization-context-authorization.mdx).

3. When using information that's only available at runtime, such as the [current time](https://github.com/openfga/openfga.dev/blob/main/../modeling/contextual-time-based-authorization.mdx) or the user's location. This scenario is better served with [Conditional Relationships](https://github.com/openfga/openfga.dev/blob/main/../modeling/conditions.mdx).

#### Important Considerations

1. Contextual tuples are ephemeral and exist only for the duration of the request.
2. When a contextual tuple is sent, and a tuple with the same user, relation and object is in the database - the one in context takes precedence and the one in the DB is ignored.
3. Contextual tuples are validated using the same authorization model rules as stored tuples.
4. There is currently a limit of 100 contextual tuples per request.
5. While token claims can be used for contextual tuples, access will continue until token expiration even if the underlying claims (like group membership) change.

#### Related Sections

<RelatedSection
  description="Learn more about the core concepts and APIs related to contextual tuples."
  relatedLinks={[
    {
      title: 'Token Claims as Contextual Tuples',
      description: 'Learn how to use token claims as contextual tuples.',
      link: '../modeling/token-claims-contextual-tuples',
      id: '../modeling/token-claims-contextual-tuples.mdx',
    },
    {
      title: 'Organization Context Authorization',
      description: 'Learn about organization context-based authorization.',
      link: '../modeling/organization-context-authorization',
      id: '../modeling/organization-context-authorization.mdx',
    },
    {
      title: 'Conditional Relationships',
      description: 'Learn about using conditions in relationship definitions.',
      link: '../modeling/conditions',
      id: '../modeling/conditions.mdx',
    }
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/interacting/contextual-tuples.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/interacting/managing-group-access.mdx -->

---
sidebar_position: 3
slug: /interacting/managing-group-access
description: Granting a group of users access to a particular object 
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  ProductConcept,
  RelatedSection,
  RelationshipTuplesViewer,
  ProductName,
  ProductNameFormat,
  WriteRequestViewer,
} from '@components/Docs';

### Managing Group Access

<DocumentationNotice />

<ProductName format={ProductNameFormat.ShortForm}/> allows you to grant a group of users access to a particular object.

<CardBox title="When to use" appearance="filled">

Relationship tuples are helpful when you want to specify that a group of users all have the same relation to an object. For example, <ProductName format={ProductNameFormat.ShortForm}/> allows you to:

- Grant a group of `engineers` `viewer` access to `roadmap.doc`
- Create a `block_list` of `members` who can't access a `document`
- Share a `document` with a `team`
- Grant `viewer` access to a `photo` to `followers` only
- Make a `file` viewable for all `users` within an `organization`
- Manage access to a `database` for `users` in a certain `locale`

</CardBox>

#### Before you start

Familiarize yourself with basic <ProductConcept /> before you continue.

<details>
<summary>

In the example below, you have the following <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" /> with two <ProductConcept section="what-is-a-type" linkName="types" />:

- `company` that can have an `employee` relation
- `document` that can have a `reader` relation.

</summary>

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'company',
        relations: {
          employee: {
            this: {},
          },
        },
        metadata: {
          relations: {
            employee: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'document',
        relations: {
          reader: {
            this: {},
          },
        },
        metadata: {
          relations: {
            reader: { directly_related_user_types: [{ type: 'company', relation: 'employee' }] },
          },
        },
      },
    ],
  }}
/>

<hr />

In addition, the following concepts are important to group access management:

##### Modeling user groups

<ProductName format={ProductNameFormat.ShortForm}/> allows you to add users to groups and grant groups access to an object. [For more information, see User Groups.](https://github.com/openfga/openfga.dev/blob/main/../modeling/user-groups.mdx)

##### <ProductName format={ProductNameFormat.ShortForm}/> concepts

- A <ProductConcept section="what-is-a-type" linkName="Type" />: a class of objects that have similar characteristics
- A <ProductConcept section="what-is-a-user" linkName="User" />: an entity in the system that can be related to an object
- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system
- An <ProductConcept section="what-is-an-object" linkName="Object" />: represents an entity in the system. Users' relationships to it can be define through relationship tuples and the authorization model
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a grouping consisting of a user, a relation and an object stored in <ProductName format={ProductNameFormat.ShortForm}/>

</details>

#### Step by step

##### 01. Adding company to the document

The following <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" /> assigns ever `employee` of a type `company`  a `reader` relationship with a particular object of type `document`, in this case `document:planning`):

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      _description: 'Every employee in the company can read document:planning',
      user: 'company:xyz#employee',
      relation: 'reader',
      object: 'document:planning',
    },
  ]}
/>

##### 02. Add an employee to the company

Below is a <ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuple" /> specifying that `Anne` is an `employee` of `company:xyz`:

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:anne',
      relation: 'employee',
      object: 'company:xyz',
    },
  ]}
/>

##### 03. Checking an individual member's access to an object

Call the Check API to verify that Anne can read `document:planning` returns true:

<CheckRequestViewer user={'user:anne'} relation={'reader'} object={'document:planning'} allowed={true} />

The same check for Becky, a different user, returns false, because Becky does not have an `employee` relationship with `company:xyz`:

<CheckRequestViewer user={'user:becky'} relation={'reader'} object={'document:planning'} allowed={false} />

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to model group."
  relatedLinks={[
    {
      title: 'Modeling User Groups',
      description: 'Learn about how to model users and groups.',
      link: '../modeling/user-groups',
      id: '../modeling/user-groups.mdx',
    },
    {
      title: 'Managing Group Membership',
      description: 'Learn about managing group membership.',
      link: './managing-group-membership',
      id: './managing-group-membership.mdx',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/interacting/managing-group-access.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/interacting/managing-group-membership.mdx -->

---
sidebar_position: 1
slug: /interacting/managing-group-membership
description: Updating a user's membership to a group by adding and removing them from it
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  RelationshipTuplesViewer,
  WriteRequestViewer,
} from '@components/Docs';

### Managing Group Membership

<DocumentationNotice />

In this guide you will learn how to update a user's membership to a group by adding and removing them from it.

<CardBox title="When to use" appearance="filled">

Suppose:

- An employee is hired at a company and thus gains access to all of the company's resources.
- An employee quits and thus loses access to all of the company's resources.
- A user joins a GitHub organization and gains access to the organizations private repositories.
- A student graduates from school and loses access to the school's facilities.

These are cases where using group membership can be helpful as you do not need to iterate over all of the group's resources to add or revoke access to particular objects. You can add a relationship tuple indicating that a user belongs to a group, or delete a tuple to indicate that a user is no longer part of the group.

</CardBox>

#### Before you start

In order to understand this guide correctly you must be familiar with some <ProductConcept /> and know how to develop the things that we will list below.

<details>
<summary>

Assume that you have the following <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />.<br />
You have two <ProductConcept section="what-is-a-type" linkName="types" />:

- `org` that can have a `member` relation
- `document` that can have a `reader` relation.

</summary>

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'org',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'document',
        relations: {
          reader: {
            this: {},
          },
        },
        metadata: {
          relations: {
            reader: { directly_related_user_types: [{ type: 'org', relation: 'member' }] },
          },
        },
      },
    ],
  }}
/>

Let us also assume that we have an `org` called "contoso" and a `document` called `planning`, and every `member` of that `org` can read the document. That is represented by having the following _<ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuple" />_ in the store:

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      _description: 'Members of the contoso org can read the planning document',
      user: 'org:contoso#member',
      relation: 'reader',
      object: 'document:planning',
    },
  ]}
/>

With the above authorization model and relationship tuples, <ProductName format={ProductNameFormat.ShortForm}/> will respond with `{"allowed":false}` when _<ProductConcept section="what-is-a-check-request" linkName="check" />_ is called to see if Anne can read `document:planning`.

<CheckRequestViewer user={'anne'} relation={'reader'} object={'document:planning'} allowed={false} />

Now let's make Anne a `member` of `org:contoso` by adding another tuple:

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'Anne is a member of the contoso org',
      user: 'user:anne',
      relation: 'member',
      object: 'org:contoso',
    },
  ]}
/>

The <ProductName format={ProductNameFormat.ShortForm}/> service will now correctly respond with `{"allowed":true}` when check is called to see if Anne can read `document:planning`, but it will still respond with `{"allowed":false}` if we ask the same question for another user called Becky, who is not a member of the group `org:contoso`.

<CheckRequestViewer user={'user:anne'} relation={'reader'} object={'document:planning'} allowed={true} />

<CheckRequestViewer user={'user:becky'} relation={'reader'} object={'document:planning'} allowed={false} />

##### Modeling user groups

You need to know how to add users to groups and grant groups access to an object. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../modeling/user-groups.mdx)

##### Managing group access

You need to know how to manage group access to an object. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/./managing-group-access.mdx)

##### <ProductName format={ProductNameFormat.ShortForm}/> concepts

- A <ProductConcept section="what-is-a-type" linkName="Type" />: a class of objects that have similar characteristics
- A <ProductConcept section="what-is-a-user" linkName="User" />: an entity in the system that can be related to an object
- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: is a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system
- An <ProductConcept section="what-is-an-object" linkName="Object" />: represents an entity in the system. Users' relationships to it can be define through relationship tuples and the authorization model
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a grouping consisting of a user, a relation and an object stored in <ProductName format={ProductNameFormat.ShortForm}/>

</details>

#### Step by step

##### 01. Revoking group membership

Imagine that every member of `org:contoso` has a `reader` relationship to 1000 documents. Now imagine that `anne` is no longer a member of `org:contoso`, so we want to revoke her access to all those documents, including `document:planning`. To accomplish this, we can simply **delete** the tuple in <ProductName format={ProductNameFormat.ShortForm}/> that specifies that Anne is a `member` of `org:contoso`.

<WriteRequestViewer
  deleteRelationshipTuples={[
    {
      user: 'user:anne',
      relation: 'member',
      object: 'org:contoso',
    },
  ]}
/>

##### 02. Validating revoked member no longer has access

Once the above relationship tuple is deleted, we can check if Anne can read `document:planning`. <ProductName format={ProductNameFormat.ShortForm}/> will return `{ "allowed": false }`.

<CheckRequestViewer user={'user:anne'} relation={'reader'} object={'document:planning'} allowed={false} />

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to model group."
  relatedLinks={[
    {
      title: 'Modeling User Groups',
      description: 'Learn about how to model users and groups.',
      link: '../modeling/user-groups',
      id: '../modeling/user-groups.mdx',
    },
    {
      title: 'Managing Group Access',
      description: 'Learn about managing group access.',
      link: './managing-group-access',
      id: './managing-group-access.mdx',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/interacting/managing-group-membership.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/interacting/managing-relationships-between-objects.mdx -->

---
sidebar_position: 2
slug: /interacting/managing-relationships-between-objects
description: Granting a user access to a particular object through a relationship with another object
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  WriteRequestViewer,
} from '@components/Docs';

### Managing Relationships Between Objects

<DocumentationNotice />

In this guide you will learn how to grant a user access to a particular object through a relationship with another object.

<CardBox title="When to use" appearance="filled">

Giving user access through a relationship with another object is helpful because it allows scaling as the number of object grows. For example:

- organization that owns many repos
- team that administers many documents

</CardBox>

#### Before you start

In order to understand this guide correctly you must be familiar with some <ProductConcept /> and know how to develop the things that we will list below.

<details>
<summary>

Assume that you have the following <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" /><br />

- a `repo` type that can have a `admin` relation

</summary>

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'repo',
        relations: {
          admin: {
            this: {},
          },
        },
        metadata: {
          relations: {
            admin: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

<hr />

In addition, you will need to know the following:

##### Direct access

You need to know how to create an authorization model and create a relationship tuple to grant a user access to an object. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../modeling/direct-access.mdx)

##### <ProductName format={ProductNameFormat.ShortForm}/> concepts

- A <ProductConcept section="what-is-a-type" linkName="Type" />: a class of objects that have similar characteristics
- A <ProductConcept section="what-is-a-user" linkName="User" />: an entity in the system that can be related to an object
- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: is a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system
- An <ProductConcept section="what-is-an-object" linkName="Object" />: represents an entity in the system. Users' relationships to it can be define through relationship tuples and the authorization model
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a grouping consisting of a user, a relation and an object stored in <ProductName format={ProductNameFormat.ShortForm}/>

</details>

#### Step by step

For the current model, a <ProductConcept section="what-is-a-user" linkName="user" /> can be related as an `admin` to an <ProductConcept section="what-is-an-object" linkName="object" /> of <ProductConcept section="what-is-a-type" linkName="type" /> `repo`. If we wanted to have Anne be related to two repos, `repo:1` and `repo:2`, we would have to add two <ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuples" />, like so:

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:anne',
      relation: 'admin',
      object: 'repo:1',
    },
    {
      user: 'user:anne',
      relation: 'admin',
      object: 'repo:2',
    },
  ]}
/>

In general, every time we wanted to add a new `admin` relationship to a `repo` we'd have to add a new tuple. This doesn't scale as the list of `repo`s and users grows.

##### 01. Modify authorization model

Another way of modeling this is to have an authorization model as follows:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'repo',
        relations: {
          admin: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'owner',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'repo_admin',
                    },
                  },
                },
              ],
            },
          },
          owner: {
            this: {},
          },
        },
        metadata: {
          relations: {
            owner: { directly_related_user_types: [{ type: 'org' }] },
            admin: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'org',
        relations: {
          repo_admin: {
            this: {},
          },
        },
        metadata: {
          relations: {
            repo_admin: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

In this model, we have:

- added a new type `org` with one relation `repo_admin`.
- added a new relation `owner` for type `repo`.
- re-defined the relation `admin` for `repo`. A user can be defined as an `admin` directly, as we have seen above, or through the `repo_admin from owner` clause. How this works, for example, is that if `user` is related as `repo_admin` to `org:xyz`, and `org:xyz` is related as `owner` to `repo:1`, then `user` is an `admin` of `repo:1`.

##### 02. Adding relationship tuples where user is another object

With this model, we can add tuples representing that an `org` is the `owner` of a `repo`. By adding following relationship tuples, we are indicating that the xyz organization is the owner of repositories with IDs `1` and `2`:

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'org:xyz',
      relation: 'owner',
      object: 'repo:1',
    },
    {
      user: 'org:xyz',
      relation: 'owner',
      object: 'repo:2',
    },
  ]}
/>

##### 03. Adding relationship tuples to the other object

Now, imagine we have a new user Becky. If we wanted to have Becky be the `admin` of all `repo`s without having to add one tuple per `repo`, all we need to do is add one tuple that says that Becky is related as `repo_admin` to `org:xyz`.

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:becky',
      relation: 'repo_admin',
      object: 'org:xyz',
    },
  ]}
/>

##### 04. Validating user access

We can now verify that Becky an `admin` of all the `repo`s owned by `org:xyz`:

<CheckRequestViewer user={'user:becky'} relation={'admin'} object={'repo:1'} allowed={true} />

<CheckRequestViewer user={'user:becky'} relation={'admin'} object={'repo:2'} allowed={true} />

##### 05. Revoking access

Suppose now that we want to prevent users from being an `admin` of `repo:1` via `org:xyz`. We can delete one tuple:

<WriteRequestViewer
  deleteRelationshipTuples={[
    {
      user: 'org:xyz',
      relation: 'owner',
      object: 'repo:1',
    },
  ]}
/>

With this change, we may now verify that Becky is no longer an `admin` of `repo:1`.

<CheckRequestViewer user={'user:becky'} relation={'admin'} object={'repo:1'} allowed={false} />

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to model relationships between objects."
  relatedLinks={[
    {
      title: 'Modeling Parent-Child Objects',
      description: 'Learn about how to cascade relationships from parent object to child object.',
      link: '../modeling/parent-child',
      id: '../modeling/parent-child.mdx',
    },
    {
      title: 'Modeling Object to Object Relationships',
      description: 'Learn about modeling patterns on objects that are not specifically tied to a user.',
      link: '../modeling/building-blocks/object-to-object-relationships',
      id: '../modeling/building-blocks/object-to-object-relationships.mdx',
    },
    {
      title: 'Modeling GitHub',
      description: 'An example of object to object relationships.',
      link: '../modeling/advanced/github',
      id: '../modeling/advanced/github.mdx',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/interacting/managing-relationships-between-objects.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/interacting/managing-user-access.mdx -->

---
sidebar_position: 3
slug: /interacting/managing-user-access
description: Granting a user access to a particular object
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  RelationshipTuplesViewer,
  WriteRequestViewer,
} from '@components/Docs';

### Managing User Access

<DocumentationNotice />

In this guide you will learn how to grant a <ProductConcept section="what-is-a-user" linkName="user" /> access to a particular <ProductConcept section="what-is-an-object" linkName="object" />.

<CardBox title="When to use" appearance="filled">

Granting access with a _<ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuple" />_ is a core part of <ProductName format={ProductNameFormat.ShortForm}/>. Without any relationship tuples, any _<ProductConcept section="what-is-a-check-request" linkName="check" />_ will fail. You should use:

- _authorization model_ to represent what **relation**s are possible between the users and objects in your system
- _relationship tuples_ to represent the facts about the relationships between users and objects in your system.

</CardBox>

#### Before you start

In order to understand this guide correctly you must be familiar with some <ProductConcept /> and know how to develop the things that we will list below.

<details>
<summary>

Assume that you have the following <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />.<br />
You have a <ProductConcept section="what-is-a-type" linkName="type" /> called `tweet` that can have a `reader`.

</summary>

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'tweet',
        relations: {
          reader: {
            this: {},
          },
        },
        metadata: {
          relations: {
            reader: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

<hr />

In addition, you will need to know the following:

##### Direct access

You need to know how to create an authorization model and create a relationship tuple to grant a user access to an object. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../modeling/direct-access.mdx)

##### <ProductName format={ProductNameFormat.ShortForm}/> concepts

- A <ProductConcept section="what-is-a-type" linkName="Type" />: a class of objects that have similar characteristics
- A <ProductConcept section="what-is-a-user" linkName="User" />: an entity in the system that can be related to an object
- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: is a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system
- An <ProductConcept section="what-is-an-object" linkName="Object" />: represents an entity in the system. Users' relationships to it can be define through relationship tuples and the authorization model
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a grouping consisting of a user, a relation and an object stored in <ProductName format={ProductNameFormat.ShortForm}/>

</details>

#### Step by step

##### 01. Adding direct relationship

For our application, we will give user Anne the `reader` relationship to a particular `tweet`. To do so we add a tuple as follows:

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      _description: 'Anne can read tweet:1',
      user: 'user:anne',
      relation: 'reader',
      object: 'tweet:1',
    },
  ]}
/>

With the above, we have added a [**direct** relationship](https://github.com/openfga/openfga.dev/blob/main/../modeling/building-blocks/direct-relationships.mdx) between Anne and `tweet:1`. When we call the Check API to see if Anne has a `reader` relationship, <ProductName format={ProductNameFormat.ShortForm}/> will say yes.

<CheckRequestViewer user={'user:anne'} relation={'reader'} object={'tweet:1'} allowed={true} />

##### 02. Removing direct relationship

Now let's change this so that Anne no longer has a `reader` relationship to `tweet:1` by deleting the tuple:

<WriteRequestViewer
  deleteRelationshipTuples={[
    {
      user: 'user:anne',
      relation: 'reader',
      object: 'tweet:1',
    },
  ]}
/>

With this, we have removed the [direct relationship](https://github.com/openfga/openfga.dev/blob/main/../modeling/building-blocks/direct-relationships.mdx) between Anne and `tweet:1`. And because our type definition for `reader` does not include any other relations, a call to the Check API will now return a negative response.

<CheckRequestViewer user={'user:anne'} relation={'reader'} object={'tweet:1'} allowed={false} />

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to manage user access."
  relatedLinks={[
    {
      title: 'Direct Access',
      description: 'Learn about how to model granting user access to an object.',
      link: '../modeling/direct-access',
      id: '../modeling/direct-access.mdx',
    },
    {
      title: 'Modeling Public Access',
      description: 'Learn about how to model granting public access.',
      link: '../modeling/public-access',
      id: '../modeling/public-access',
    },
    {
      title: 'How to update relationship tuples',
      description: 'Learn about how to update relationship tuples in SDK.',
      link: '../getting-started/update-tuples',
      id: '../getting-started/update-tuples',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/interacting/managing-user-access.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/interacting/overview.mdx -->

---
id: overview
title: 'Interacting with the API'
slug: /interacting
sidebar_position: 0
description: Programmatically writing authorization related data and interact with the API
---

import { DocumentationNotice, IntroCard, CardGrid, ProductName, ProductNameFormat } from '@components/Docs';

<DocumentationNotice />

This section helps you integrate <ProductName format={ProductNameFormat.ShortForm}/> with your system. To do this, you will learn how to programmatically write authorization related data to <ProductName format={ProductNameFormat.ShortForm}/>.

<IntroCard
  title="When to use"
  description="This section is useful if you have defined an authorization model and want to understand how to write authorization data to {ProductName} to represent the state of your system."
/>

### Content

<CardGrid
  bottom={[
    {
      title: 'Manage User Access',
      description: "Write relationship tuples to manage a user's access to an object.",
      to: 'interacting/managing-user-access',
    },
    {
      title: 'Manage Group Access',
      description: 'Write relationship tuples to manage access to an object for all members of a group.',
      to: 'interacting/managing-group-access',
    },
    {
      title: 'Manage Group Membership',
      description: 'Write relationship tuples to manage the users that are members of a group.',
      to: 'interacting/managing-group-membership',
    },
    {
      title: 'Manage Relationships Between Object',
      description:
        'Write relationship tuples to manage how two objects are related. E.g. parent folder and child document.',
      to: 'interacting/managing-relationships-between-objects',
    },
    {
      title: 'Transactional Writes',
      description: 'Write multiple relationship tuples in a single request, so all writes either succeed or fail.',
      to: 'interacting/transactional-writes',
    },
    {
      title: 'Relationship Queries',
      description: 'An overview of how to use the Check, Read, Expand, and ListObject APIs.',
      to: 'interacting/relationship-queries',
    },
    {
      title: 'Search with Permissions',
      description: 'Implementing search with OpenFGA.',
      to: 'interacting/search-with-permissions',
    },
]}
/>


<!-- End of openfga/openfga.dev/docs/content/interacting/overview.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/interacting/read-tuple-changes.mdx -->

---
title: How to get tuple changes
sidebar_position: 1
slug: /interacting/read-tuple-changes
description: Getting tuple changes
---

import {
  AuthzModelSnippetViewer,
  SupportedLanguage,
  languageLabelMap,
  DocumentationNotice,
  SdkSetupHeader,
  ProductName,
  ProductNameFormat,
  ReadChangesRequestViewer,
  SdkSetupPrerequisite,
} from '@components/Docs';
import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

### How to get tuple changes

<DocumentationNotice />

This section illustrates how to call the Read Changes API to get the list of relationship tuple changes that happened in your store, in the exact order that they happened. The API response includes tuples that have been added or removed in your store. It does not include other changes, like updates to your authorization model and adding new assertions.

#### Before you start

<Tabs groupId="languages">
<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/../getting-started/install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/../modeling) and [added some _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/../getting-started/update-tuples.mdx#02-calling-write-api-to-add-new-relationship-tuples).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>
<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/../getting-started/install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/../modeling) and [added some _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/../getting-started/update-tuples.mdx#02-calling-write-api-to-add-new-relationship-tuples).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>

<TabItem value={SupportedLanguage.DOTNET_SDK} label={languageLabelMap.get(SupportedLanguage.DOTNET_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/../getting-started/install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/../modeling).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>
<TabItem value={SupportedLanguage.PYTHON_SDK} label={languageLabelMap.get(SupportedLanguage.PYTHON_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/../getting-started/install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/../modeling).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>
<TabItem value={SupportedLanguage.JAVA_SDK} label={languageLabelMap.get(SupportedLanguage.JAVA_SDK)}>

1. <SdkSetupPrerequisite />
2. You have [installed the SDK](https://github.com/openfga/openfga.dev/blob/main/../getting-started/install-sdk.mdx).
3. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/../modeling).
4. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>
<TabItem value={SupportedLanguage.CURL} label={languageLabelMap.get(SupportedLanguage.CURL)}>

1. <SdkSetupPrerequisite />
2. You have [configured the _authorization model_](https://github.com/openfga/openfga.dev/blob/main/../modeling) and [added some _relationship tuples_](https://github.com/openfga/openfga.dev/blob/main/../getting-started/update-tuples.mdx#02-calling-write-api-to-add-new-relationship-tuples).
3. You have loaded `FGA_STORE_ID` and `FGA_API_URL` as environment variables.

</TabItem>
</Tabs>

#### Step by step

To get a chronologically ordered list of tuples that have been written or deleted in your store, you can do so by calling the Read Changes API.

##### 01. Configure The <ProductName format={ProductNameFormat.ShortForm}/> API Client

First you will need to configure the API client.

<Tabs groupId="languages">
<TabItem value={SupportedLanguage.JS_SDK} label={languageLabelMap.get(SupportedLanguage.JS_SDK)}>

<SdkSetupHeader lang="js-sdk" />

</TabItem>
<TabItem value={SupportedLanguage.GO_SDK} label={languageLabelMap.get(SupportedLanguage.GO_SDK)}>

<SdkSetupHeader lang="go-sdk" />

</TabItem>

<TabItem value={SupportedLanguage.DOTNET_SDK} label={languageLabelMap.get(SupportedLanguage.DOTNET_SDK)}>

<SdkSetupHeader lang="dotnet-sdk" />

</TabItem>

<TabItem value={SupportedLanguage.PYTHON_SDK} label={languageLabelMap.get(SupportedLanguage.PYTHON_SDK)}>

<SdkSetupHeader lang="python-sdk" />

</TabItem>

<TabItem value={SupportedLanguage.JAVA_SDK} label={languageLabelMap.get(SupportedLanguage.JAVA_SDK)}>

<SdkSetupHeader lang="java-sdk" />

</TabItem>


<TabItem value={SupportedLanguage.CURL} label={languageLabelMap.get(SupportedLanguage.CURL)}>

To obtain the [access token](https://auth0.com/docs/get-started/authentication-and-authorization-flow/client-credentials-flow/call-your-api-using-the-client-credentials-flow):

<SdkSetupHeader lang="curl" />

</TabItem>

</Tabs>

##### 02. Get changes for all object types

To get a paginated list of changes that happened in your store:

<ReadChangesRequestViewer
  pageSize={25}
  skipSetup={true}
  allowedLanguages={[
    SupportedLanguage.JS_SDK,
    SupportedLanguage.GO_SDK,
    SupportedLanguage.DOTNET_SDK,
    SupportedLanguage.PYTHON_SDK,
    SupportedLanguage.JAVA_SDK,
    SupportedLanguage.CLI,
    SupportedLanguage.CURL,
  ]}
/>

The result will contain an array of up to 25 tuples, with the operation (`write` or `delete`), and the timestamp in which that operation took place. The result will also contain a continuation token. Save the continuation token in persistent storage between calls so that it is not lost and you do not have to restart from scratch on system restart or on error.

You can then use this token to get the next set of changes:

<ReadChangesRequestViewer
  pageSize={25}
  continuationToken={'eyJwayI6IkxBVEVTVF9OU0NPTkZJR19hdXRoMHN0b3JlIiwic2siOiIxem1qbXF3MWZLZExTcUoyN01MdTdqTjh0cWgifQ=='}
  skipSetup={true}
  allowedLanguages={[
    SupportedLanguage.JS_SDK,
    SupportedLanguage.GO_SDK,
    SupportedLanguage.DOTNET_SDK,
    SupportedLanguage.PYTHON_SDK,
    SupportedLanguage.JAVA_SDK,
    SupportedLanguage.CLI,
    SupportedLanguage.CURL,
  ]}
/>

Once there are no more changes to retrieve, the API will return the same token as the one you sent. Save the token in persistent storage to use at a later time.

:::note

- The default page size is 50. The maximum page size allowed is 100.
- The API response does not expand the tuples. If you wrote a tuple that includes a userset, like `{"user": "group:abc#member", "relation": "owner": "doc:budget"}`, the Read Changes API will return that exact tuple.

:::

##### 03. Get changes for a specific object type

Imagine you have the following authorization model:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'group',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'folder',
        relations: {
          owner: {
            this: {},
          },
        },
        metadata: {
          relations: {
            owner: { directly_related_user_types: [{ type: 'group', relation: 'member' }, { type: 'user' }] },
          },
        },
      },
      {
        type: 'doc',
        relations: {
          owner: {
            this: {},
          },
        },
        metadata: {
          relations: {
            owner: { directly_related_user_types: [{ type: 'group', relation: 'member' }, { type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

It is possible to get a list of changes that happened in your store that relate only to one specific object type, like `folder`, by issuing a call like this:

<ReadChangesRequestViewer
  pageSize={25}
  type={'folder'}
  skipSetup={true}
  allowedLanguages={[
    SupportedLanguage.JS_SDK,
    SupportedLanguage.GO_SDK,
    SupportedLanguage.DOTNET_SDK,
    SupportedLanguage.PYTHON_SDK,
    SupportedLanguage.JAVA_SDK,
    SupportedLanguage.CLI,
    SupportedLanguage.CURL,
  ]}
/>

The response will include a continuation token. In subsequent calls, you have to include the token and the `type`. (If you send this continuation token without the `type` parameter set, you will get an error).

<ReadChangesRequestViewer
  pageSize={25}
  type={'folder'}
  continuationToken={'eyJwayI6IkxBVEVTVF9OU0NPTkZJR19hdXRoMHN0b3JlIiwic2siOiIxem1qbXF3MWZLZExTcUoyN01MdTdqTjh0cWgifQ=='}
  skipSetup={true}
  allowedLanguages={[
    SupportedLanguage.JS_SDK,
    SupportedLanguage.GO_SDK,
    SupportedLanguage.DOTNET_SDK,
    SupportedLanguage.PYTHON_SDK,
    SupportedLanguage.JAVA_SDK,
    SupportedLanguage.CLI,
    SupportedLanguage.CURL,
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/interacting/read-tuple-changes.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/interacting/relationship-queries.mdx -->

---
title: 'Relationship Queries: Check, Read, Expand, and ListObjects'
sidebar_position: 6
slug: /interacting/relationship-queries
description: An overview of how to use the Check, Read, Expand, and ListObject APIs
---

import {
  AuthzModelSnippetViewer,
  CheckRequestViewer,
  BatchCheckRequestViewer,
  DocumentationNotice,
  ExpandRequestViewer,
  ListObjectsRequestViewer,
  ListUsersRequestViewer,
  ReadRequestViewer,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  RelationshipTuplesViewer,
} from '@components/Docs';

### Relationship Queries: Check, Read, Expand, ListObjects and ListUsers

<DocumentationNotice />

In this guide you will learn the uses of and limitations for the Check, Read, Expand, and ListObjects API endpoints.

#### Before you start

In order to understand this guide correctly you must be familiar with some <ProductConcept /> and know how to develop the things that we will list below.

<details>
<summary>

Assume that you have the following <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />.<br />
You have a <ProductConcept section="what-is-a-type" linkName="type" /> called `document` that can have a `reader`
and `writer`. All writers are readers. `bob` has a `writer` relationship with `document:planning`.

</summary>

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          writer: {
            this: {},
          },
          reader: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    object: '',
                    relation: 'writer',
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            writer: { directly_related_user_types: [{ type: 'user' }] },
            reader: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      _description: 'Bob has writer relationship with planning document',
      user: 'user:bob',
      relation: 'writer',
      object: 'document:planning',
    },
  ]}
/>

<hr />

In addition, you will need to know the following:

##### Direct access

You need to know how to create an authorization model and create a relationship tuple to grant a user access to an object. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../modeling/direct-access.mdx)

##### <ProductName format={ProductNameFormat.ShortForm}/> concepts

- A <ProductConcept section="what-is-a-type" linkName="Type" />: a class of objects that have similar characteristics
- A <ProductConcept section="what-is-a-user" linkName="User" />: an entity in the system that can be related to an object
- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: is a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system
- An <ProductConcept section="what-is-an-object" linkName="Object" />: represents an entity in the system. Users' relationships to it can be define through relationship tuples and the authorization model
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a grouping consisting of a user, a relation and an object stored in <ProductName format={ProductNameFormat.ShortForm}/>

</details>

#### Check

##### What is it for?

The [Check API](https://github.com/openfga/openfga.dev/blob/main//api/service#Relationship%20Queries/Check) is an API endpoint that returns whether the user has a certain relationship with an object. <ProductName format={ProductNameFormat.ShortForm}/> will resolve all prerequisite relationships to establish whether a relationship exists.

##### When to use?

Check can be called if you need to establish whether a particular user has a specific relationship with a particular object.

For example, you can call check to determine whether `bob` has a `reader` relationship with `document:planning`.

<CheckRequestViewer user={'user:bob'} relation={'reader'} object={'document:planning'} allowed={true} />

The <ProductName format={ProductNameFormat.ShortForm}/> API will return `true` because there is an implied relationship as

- every `writer` is also a `reader`
- `bob` is a `writer` for `document:planning`

##### Caveats and when not to use it

Check is designed to answer the question "Does user:X have relationship Y with object:Z?". It is _not_ designed to answer the following questions:

- "Who has relationship Y with object:Z?"
- "What are the objects that userX has relationship Y with?"
- "Why does user:X have relationship Y with object:Z?"

#### Batch Check

##### What is it for?

The [Batch Check API](https://github.com/openfga/openfga.dev/blob/main//api/service#Relationship%20Queries/BatchCheck) is an API endpoint that allows you to check multiple user-object-relationship combinations in a single request. 

##### When to use?

Batching authorization checks together in a single request significantly reduces overall network latency.

Two scenarios are common to use Batch Check:
1. When determining if the user has access to a list of objects (such as [Option 1 in Search with Permissions](https://github.com/openfga/openfga.dev/blob/main/./search-with-permissions.mdx#option-1-search-then-check)), filter and sort on your database, then call `/batch-check`. Repeat to perform pagination.
2. When determining fields on a web page the user has access to, call `/batch-check` for every relation necessary to show/hide each field.  

For example, you can call Batch Check to determine whether `bob` has `can_view_name`, `can_view_dob`, and `can_view_ssn` relationships with `patient_record:1`.

<BatchCheckRequestViewer
  checks={[
    {
      user: 'user:bob',
      relation: 'can_view_name',
      object: 'patient_record:1',
      correlation_id: '1',
      allowed: true
    },
    {
      user: 'user:bob',
      relation: 'can_view_dob',
      object: 'patient_record:1',
      correlation_id: '2',
      allowed: true
    },
    {
      user: 'user:bob',
      relation: 'can_view_ssn',
      object: 'patient_record:1',
      correlation_id: '3',
      allowed: false
    }
  ]}
  skipSetup={true}
/>

The <ProductName format={ProductNameFormat.ShortForm}/> API will return `true` depending on the level of access assigned to that user and the implied relationships inherited in the authorization model.

##### Caveats and when not to use it

If you are making less than 10 checks, it may be faster to call the [Check API](https://github.com/openfga/openfga.dev/blob/main//api/service#Relationship%20Queries/Check) in parallel instead of Batch Check.

:::note
The BatchCheck endpoint is currently supported by the following SDKs:
- Go SDK ([>=0.7.0](https://github.com/openfga/go-sdk/releases/tag/v0.7.0))
- JavaScript SDK ([>=v0.8.0](https://github.com/openfga/js-sdk/releases/tag/v0.8.0))
- Python SDK ([>=v0.9.0](https://github.com/openfga/python-sdk/releases/tag/v0.9.0))
- Java SDK ([>=0.8.1](https://github.com/openfga/java-sdk/releases/tag/v0.8.1))
- Support for .NET is in progress and coming soon.

In SDKs that support the `BatchCheck` endpoint (server-side batch checks), the previous `BatchCheck` method has been renamed to `ClientBatchCheck`. `ClientBatchCheck` performs client-side batch checks by making multiple check requests with limited parallelization.

The .NET SDK does not yet support the `BatchCheck` endpoint (coming soon). Until then, the `BatchCheck` method maintains its current behavior, performing client-side batch checks equivalent to `ClientBatchCheck` in other SDKs.

Refer to the README for each SDK for more information. Refer to the release notes of the relevant SDK version for more information on how to migrate from client-side to the server-side `BatchCheck`.
:::

#### Read

##### What Is It For?

The [Read API](https://github.com/openfga/openfga.dev/blob/main//api/service#Relationship%20Tuples/Read) is an API endpoint that returns the relationship tuples that are stored in the system that satisfy a query.

##### When to use?

Read can be called if you need to get all the stored relationship tuples that relate:

1. [**user + relation + object**](#1-read-a-tuple-related-to-a-particular-user-relation-and-object)
2. [**user + relation + object type**](#2-read-all-tuples-related-to-a-particular-user-relation-and-object-type)
3. [**user + object** with any relation](#3-read-all-tuples-related-to-a-particular-user-and-object-with-any-relation)
4. [**user + object type** with any relation](#4-read-all-tuples-related-to-a-particular-user-and-object-type-with-any-relation)
5. [**relation + object** for any user](#5-read-all-tuples-related-to-a-particular-relation-and-object-for-any-user)
6. [**object** with any user and relation](#6-read-all-tuples-related-to-a-particular-object-with-any-user-or-relation)
7. [**all** with any user, relation, or object](#7-read-all-tuples-for-any-user-relation-or-object)

###### 1. Read a tuple related to a particular user, relation, and object

For example, to query if `bob` has a `writer` relationship on `document:planning` (essentially finding out if a tuple exists), one can ask

<ReadRequestViewer
  user={'user:bob'}
  relation={'writer'}
  object={'document:planning'}
  tuples={[
    {
      user: 'user:bob',
      relation: 'writer',
      object: 'document:planning',
    },
  ]}
/>

###### 2. Read all tuples related to a particular user, relation, and object type

For example, to query all the stored relationship tuples `bob` has a `writer` relationship with on type `document:`, one can ask

<ReadRequestViewer
  user={'user:bob'}
  relation={'writer'}
  object={'document:'}
  tuples={[
    {
      user: 'user:bob',
      relation: 'writer',
      object: 'document:planning',
    },
  ]}
/>

###### 3. Read all tuples related to a particular user and object with any relation

For example, to query all the stored relationship tuples `bob` has on type `document:planning`, one can ask

<ReadRequestViewer
  user={'user:bob'}
  object={'document:planning'}
  tuples={[
    {
      user: 'user:bob',
      relation: 'writer',
      object: 'document:planning',
    },
  ]}
/>

###### 4. Read all tuples related to a particular user and object type with any relation

For example, to query all the stored relationship tuples `bob` has on object type `document:`, one can ask

<ReadRequestViewer
  user={'user:bob'}
  object={'document:'}
  tuples={[
    {
      user: 'user:bob',
      relation: 'writer',
      object: 'document:planning',
    },
  ]}
/>

###### 5. Read all tuples related to a particular relation and object for any user

For example, to query all the stored relationship tuples which have the `writer` relation on object `document:planning`, one can ask

<ReadRequestViewer
  relation={'writer'}
  object={'document:planning'}
  tuples={[
    {
      user: 'user:bob',
      relation: 'writer',
      object: 'document:planning',
    },
  ]}
/>

###### 6. Read all tuples related to a particular object with any user or relation

For example, to query all the stored relationship tuples for object `document:planning`, one can ask

<ReadRequestViewer
  object={'document:planning'}
  tuples={[
    {
      user: 'user:bob',
      relation: 'writer',
      object: 'document:planning',
    },
  ]}
/>

###### 7. Read all tuples for any user, relation, or object

For example, to query all stored relationship tuples, one can ask

<ReadRequestViewer
  tuples={[
    {
      user: 'user:bob',
      relation: 'writer',
      object: 'document:planning',
    },
  ]}
/>

##### Caveats and when not to use it

The Read API will only return all the stored relationships that match the query specification.
It does not expand or traverse the graph by taking the authorization model into account.

For example, if you specify that `writers` are `viewers` in the authorization model, the Read API will ignore that and it will return tuples where a user is a `viewer` if and only if the `(user_id, "viewer", object_type:object_id)` relationship tuple exists in the system.

In the following case, although all `writers` have reader `relationships` for document objects and `bob` is a `writer` for `document:planning`, if you query for all objects that `bob` has `reader` relationships, it will not return `document:planning`.

<ReadRequestViewer user={'user:bob'} relation={'reader'} object={'document:'} tuples={[]} />

:::info
Although bob is a writer to document:planning and every writer is also a reader, the Read API will return an empty list because there are no stored relationship tuples that relate bob to document:planning as reader.
:::

#### Expand

##### What is it for?

The [Expand API](https://github.com/openfga/openfga.dev/blob/main//api/service#/Relationship%20Queries/Expand) returns all users (including users and usersets) that have a specific relationship with an object.
The response is represented as a tree of users or usersets. To build the full graph of access, you would need to recursively call expand on the leaves returned from the previous expand call.

##### When to use?

Expand is used for debugging and to understand why a user has a particular relationship with a specific object.

For example, to understand why `bob` can have a `reader` relationship with `document:planning`, one could first call

<ExpandRequestViewer relation={'reader'} object={'document:planning'} />

The result of this call will be like

```json
{
  "tree":{
    "root":{
      "type":"document:planning#reader",
        "leaf":{
          "computed":{
            "userset":"document:planning#writer"
          }
        }
      }
    }
  }
}
```

The returned tree will contain `writer`, for which we will call

<ExpandRequestViewer relation={'writer'} object={'document:planning'} />

The result of this call will be like

```json
{
  "tree":{
    "root":{
      "type":"document:planning#writer",
        "leaf":{
          "users":{
            "users":[
              "user:bob"
            ]
          }
        }
      }
    }
  }
}
```

From there, we will learn that

- those related to `document:planning` as `reader` are all those who are related to that document as `writer`
- `bob` is related to `document:planning` as `writer`

#### ListObjects

##### What is it for?

The [ListObjects API](https://github.com/openfga/openfga.dev/blob/main//api/service#/Relationship%20Queries/ListObjects) is an API endpoint that returns the list of all the objects of a particular type that a specific user has a specific relationship with.

It provides a solution to the [Search with Permissions (Option 3)](https://github.com/openfga/openfga.dev/blob/main/./search-with-permissions.mdx#option-3-build-a-list-of-ids-then-search) use case for access-aware filtering on small object collections.

##### When to use?

Use the ListObjects API to get what objects a user can see based on the relationships they have. See [Search with Permissions](https://github.com/openfga/openfga.dev/blob/main/./search-with-permissions.mdx) for more guidance.

<ListObjectsRequestViewer
  authorizationModelId="01HVMMBCMGZNT3SED4Z17ECXCA"
  objectType="document"
  relation="reader"
  user="user:bob"
  contextualTuples={[{ object: 'document:otherdoc', relation: 'reader', user: 'user:bob' }]}
  expectedResults={['document:otherdoc', 'document:planning']}
/>

There's two variations of the List Objects API.

- The [standard version](https://github.com/openfga/openfga.dev/blob/main//api/service#Relationship%20Queries/ListObjects), which waits until all results are ready and sends them in one response.
- The [streaming version](https://github.com/openfga/openfga.dev/blob/main//api/service#Relationship%20Queries/StreamedListObjects), which should be used if you want the individual results as soon as they become available.

##### Caveats

ListObjects will return the results found within the time allotted (`listObjectsDeadline`, default: `3s`) up to the maximum number of results configured (`listObjectsMaxResults`, default: `1000`). See [Configuring the Server](https://github.com/openfga/openfga.dev/blob/main/../getting-started/setup-openfga/configure-openfga.mdx)) for more on how to change the default configuration.

- If you set `listObjectsDeadline` to `1s`, the server will spend at most 1 second finding results.
- If you set `listObjectsMaxResults` to `10`, the server will return, at most, 10 objects.

If the number of objects of that type is high, you should set a high value for `listObjectsDeadline`. If the number of objects of that type the user could have access to is high, you should set a high value for `listObjectsMaxResults`.

#### ListUsers

##### What is it for?

The [ListUsers API](https://github.com/openfga/openfga.dev/blob/main//api/service#/Relationship%20Queries/ListUsers) is an API endpoint that that returns all users of a given type that have a specified relationship with an object.

##### When to use?

Use the ListUsers API to get which users have a relation to a specific object. 

<ListUsersRequestViewer
  authorizationModelId="01HVMMBCMGZNT3SED4Z17ECXCA"
  objectType="document"
  objectId="planning"
  relation="viewer"
  userFilterType="user"
  expectedResults={{
    users: [
      { object: { type: "user", id: "anne" }}, 
      { object: { type: "user", id: "beth" }}
    ]
  }}
/>

##### Caveats

ListUsers will return the results found within the time allotted (`listUsersDeadline`, default: `3s`) up to the maximum number of results configured (`listUsersMaxResults`, default: `1000`). See [Configuring the Server](https://github.com/openfga/openfga.dev/blob/main/../getting-started/setup-openfga/configure-openfga.mdx)) for more on how to change the default configuration.

- If you set `listUsersDeadline` to `1s`, the server will spend at most 1 second finding results.
- If you set `listUsersMaxResults` to `10`, the server will return, at most, 10 objects.

If the number of users matching that filter is high, you should set a high value for `listUsersDeadline`. If the number of users matching that filter that could have that relation with the object is high, you should set a high value for `listUsersMaxResults`.

#### Summary

|             | Check                                                         | Read                                                   | Expand                                                  | ListObjects                                                                        | ListUsers                                                                        |
| ----------- | ------------------------------------------------------------- | ------------------------------------------------------ | ------------------------------------------------------- | ---------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| Purpose     | Check if user has particular relationship with certain object | Return all stored relationship tuples that match query | Expand the specific relationship on a particular object | List all objects of a particular type that a user has a specific relationship with | List all users of a particular type that have a relation to a specific object |
| When to use | Validate if user X can perform Y on object Z                  | List stored relationships in system                    | Understand why user X can perform Y on object Z         | Filter the objects a user has access to                                            | List the users that have access to an object                                            |

#### Related Sections

<RelatedSection
  description="Check out this additional content for more information on how to query relationships."
  relatedLinks={[
    {
      title: 'Check API Reference',
      description: 'Official reference guide for the Check API',
      link: '/api/service#Relationship%20Queries/Check',
    },
    {
      title: 'Read API Reference',
      description: 'Official reference guide for the Read API',
      link: '/api/service#Relationship%20Tuples/Read',
    },
    {
      title: 'Expand API Reference',
      description: 'Official reference guide for the Expand API',
      link: '/api/service#Relationship%20Queries/Expand',
    },
    {
      title: 'ListObjects API Reference',
      description: 'Official reference guide for the ListObjects API',
      link: '/api/service#Relationship%20Queries/ListObjects',
    },
    {
      title: 'ListUsers API Reference',
      description: 'Official reference guide for the ListUsers API',
      link: '/api/service#Relationship%20Queries/ListUsers',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/interacting/relationship-queries.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/interacting/search-with-permissions.mdx -->

---
title: Search With Permissions
description: Integrating FGA into your search
sidebar_position: 1
slug: /interacting/search-with-permissions
---

import { DocumentationNotice, ProductName, ProductNameFormat, UpdateProductNameInLinks } from '@components/Docs';

### Search With Permissions

<DocumentationNotice />

Once you implement fine-grained authorization to protect your resources, search becomes a more complex problem, because the user's access to each resource now has to be validated before the resource can be shown.

The search problem can then be summarized as:

> "Given a particular search filter and a sort order, what objects can the user access"?

The <ProductName format={ProductNameFormat.ShortForm}/> service does not store object metadata (names of files, creation dates, time of last update, etc), which means completing any search request by filtering and sorting according to certain criteria will require data from your database.

The services responsible for performing these actions are:

- Filter: Your database
- Sort: Your database
- Authorize: <ProductName format={ProductNameFormat.ShortForm}/>

To return the set of results that match the user's search query, you will need to get the intersection of the results from the services above.

#### Possible options

There are three possible ways to do this:

##### Option 1: Search, then check

Pre-filter, then call <ProductName format={ProductNameFormat.ShortForm}/> Batch Check endpoint.

1. Filter and sort on your database.
1. Call [`/batch-check`](https://github.com/openfga/openfga.dev/blob/main/./relationship-queries.mdx#batch-check) to check access for multiple objects in a single request.
1. Filter out objects the user does not have access to.
1. Return the filtered result to the user.

##### Option 2: Build a local index from changes endpoint, search, then check

Consume the `GET /changes` endpoint to create a local index you can use to do an intersection on the two sets of results.

1. Call the <UpdateProductNameInLinks link="/api/service#Relationship%20Tuples/ReadChanges" name="{ProductName} changes API" />.
1. For the particular authorization model version(s) you are using in production, flatten/expand the changes (e.g. `user:anne, writer, doc:planning` becomes two tuples: `user:anne, writer, doc:planning` and `user:anne, reader, doc:planning`).
1. Build the intersection between the objects in your database and the flattened/expanded state you created.
1. You can then call `/check` on each resource in the resulting set before returning the response to filter out any resource with permissions revoked but whose authorization data has not made it into your index yet.

##### Option 3: Build a list of IDs, then search

Call the `GET /list-objects` API to get a list of object IDs the user has access to, then run the filter restricting by the object IDs returned.

1. Call the <UpdateProductNameInLinks link="/api/service#Relationship%20Queries/ListObjects" name="{ProductName} List Objects API" />. to get the list of all resources a user can access.
1. Pass in the set of object IDs to the database query to limit the search.
1. Return the filtered result to the user.

Be aware that the performance characteristics of the ListObjects endpoint vary drastically depending on the model complexity, number of tuples, and the relations it needs to evaluate. Relations with `and` or `but not` are more expensive to evaluate than relations with `or`.

#### Choosing the best option

Which option to choose among the three listed above depends on the following criteria:

1. Number of objects that your database can return from a search query
2. Number of objects of a certain type the user could have access to
3. Percentage of objects in a type the user could have access to

Consider the following scenarios:

**A.** The _number of objects a search query could return from the database_ is _low_.

**[Search then Check](#option-1-search-then-check)** is the recommended solution.

Use-case: Situations where the search query can be optimized to return a small number of results.

**B.** The _number of objects of a certain type the user could have access to_ is _low_, and the _percentage of objects in a namespace a user could have access to_ is _high_.

**[Search then Check](#option-1-search-then-check)** is recommended to get the final list of results.

Note that this use case, because the user has access to a low number of objects which are still a high percentage of the total objects in the system, that means that the total number of objects in the system is low.

**C.** The _number of objects of a certain type the user could have access to_ is _low_ (~ 1000), and the _percentage of the total objects that the user can have access to_ is also _low_.

In this case, using the `GET /list-objects` would make sense. You can query this API to get a list of object IDs and then pass these IDs to your filter function to limit the search to them.

As this number increases, this solution becomes impractical, because you would need to paginate over multiple pages to get the entire list before being able to search and sort. A partial list from the API is not enough, because you won't be able to sort using it.

So while **[List of IDs then Search](#option-3-build-a-list-of-ids-then-search)** would be useful for this in some situations, we would recommend **[Local Index from Changes Endpoint, Search then Check](#option-2-build-a-local-index-from-changes-endpoint-search-then-check)** for the cases when the number of objects is high enough. 

**D.** The _number of objects of a certain type the user could have access to_ is _high_, and the _percentage of the total objects that the user can have access to_ is _low_.

The recommended option for this case is to use **[Local Index from Changes Endpoint, Search then Check](#option-2-build-a-local-index-from-changes-endpoint-search-then-check)**.

- _List of IDs then Search_ would not work because you would have to get and paginate across thousands or tens of thousands (or in some cases more) of results from <ProductName format={ProductNameFormat.ShortForm}/>, only after you have retrieved the entire set can you start searching within your database for matching results. This would mean that your user could be waiting for a long time before they can start seeing results.

- _Search then Check_ would also not be ideal, as you will be retrieving and checking against a lot of items and discarding most of them.

Use case: Searching in Google Drive, where the list of documents and folders that a user has access to can be very high, but it still is a small percentage of the entire set of documents in Google Drive.

You can consider the following strategies to transform this scenario to a **type B** one:

- Duplicate logic from the authorization model when querying your database. For example, in a multi-tenant scenario, you can filter all resources based on the tenant the user is logged-in to. Duplicating logic from the authorization model is not ideal, but it can be a reasonable trade-off.

- Retrieve a higher-level resource ID list with lower cardinality for efficient filtering. For example, in a document management application, first obtain the list of accessible folders for the user. You can then filter documents by these folders in your database query. This approach increases the likelihood that the user can access the documents in those folders, optimizing the query’s effectiveness.

**E.** The _number of objects of a certain type the user could have access to_ is _high_, and the _percentage of the total objects that the user can have access to_ is also _high_.

In this case a **[Local Index from Changes Endpoint, Search then Check](#option-2-build-a-local-index-from-changes-endpoint-search-then-check)** would be useful. If you do not want to maintain a local index, and if the user can access a high percentage of the total, meaning that the user is more likely than not to have access to the results returned by the search query, then **[Search then Check](#option-1-search-then-check)** would work just as well.

Use-case: Searching on Twitter. Most Twitter users have their profiles set to public, so the user is more likely to have access to the tweets when performing a search. So searching first then running checks against the set of returned results would be appropriate.

#### Summary

| Scenario | Use Case                                                                                                                                                                       | # of objects returned from database query | # of objects user can access in a type | % of objects user can access in a type | Preferred Option                                                                                                             |
|----------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------|----------------------------------------|----------------------------------------|------------------------------------------------------------------------------------------------------------------------------|
| A        | Search criteria enough to narrow down results                                                                                                                                  | Low                                       | -                                      | -                                      | [1](#option-1-search-then-check)                                                                                             |
| B        | Few objects the user has access to, but still a high % of total objects                                                                                                        | Low                                       | Low                                    | High                                   | [1](#option-1-search-then-check)                                                                                             |
| C        | Cannot narrow down search results, very high probability search returns objects user cannot access, total number of objects user can access is low enough to fit in a response | High                                      | Low                                    | Low                                    | [3](#option-3-build-a-list-of-ids-then-search) or [2](#option-2-build-a-local-index-from-changes-endpoint-search-then-check) |
| D        | Google Drive: User has access to a lot of documents, but low percentage from total                                                                                             | High                                      | High                                   | Low                                    | [2](#option-2-build-a-local-index-from-changes-endpoint-search-then-check)                                                   |
| E        | Twitter Search: Most profiles are public, and the user can access them                                                                                                         | High                                      | High                                   | High                                   | [1](#option-1-search-then-check) or [2](#option-2-build-a-local-index-from-changes-endpoint-search-then-check)               |


<!-- End of openfga/openfga.dev/docs/content/interacting/search-with-permissions.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/interacting/transactional-writes.mdx -->

---
sidebar_position: 2
slug: /interacting/transactional-writes
description: Updating multiple relationship tuples in a single transaction
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  DocumentationNotice,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  RelationshipTuplesViewer,
  WriteRequestViewer,
  SupportedLanguage,
} from '@components/Docs';

### Transactional Writes

<DocumentationNotice />

Using<ProductName format={ProductNameFormat.ShortForm}/>, you can update multiple <ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuples" /> in a single transaction.

<CardBox title="When to use" appearance="filled">

Updating multiple relationship tuples can keep your system state consistent.

</CardBox>

#### Before you start

Familiarize yourself with basic <ProductConcept /> before completing this guide.

<details>
<summary>

In the following <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />, there is <ProductConcept section="what-is-a-type" linkName="type" /> called `tweet` that can have a `reader`. There is another type called `user` that can have a `follower` and `followed_by` relationship.

</summary>

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'tweet',
        relations: {
          viewer: {
            this: {},
          },
        },
        metadata: {
          relations: {
            viewer: {
              directly_related_user_types: [
                { type: 'user' },
                { type: 'user', wildcard: {} },
                { type: 'user', relation: 'follower' },
              ],
            },
          },
        },
      },
      {
        type: 'user',
        relations: {
          follower: {
            this: {},
          },
          followed_by: {
            this: {},
          },
        },
        metadata: {
          relations: {
            follower: { directly_related_user_types: [{ type: 'user' }] },
            followed_by: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

<hr />

In addition:

##### Direct access

Creating an authorization model and a relationship tuple grants a user access to an object. To learn more, [read about Direct Access.](https://github.com/openfga/openfga.dev/blob/main/../modeling/direct-access.mdx)

##### Modeling public access

The following example uses public access. To learn more, [read about Public Access.](https://github.com/openfga/openfga.dev/blob/main/../modeling/direct-access.mdx)

##### <ProductName format={ProductNameFormat.ShortForm}/> concepts

- A <ProductConcept section="what-is-a-type" linkName="Type" />: a class of objects that have similar characteristics
- A <ProductConcept section="what-is-a-user" linkName="User" />: an entity in the system that can be related to an object
- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: is a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system
- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a group stored in <ProductName format={ProductNameFormat.ShortForm}/> that consists of a user, a relation, and an object

</details>

#### Step by step

##### 01. Add and remove relationship tuples in the same transaction

A call to the Write API can add or delete tuples in your store. For example, the following tuple makes `tweet:1` public by making everyone a `viewer`:

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:*',
      relation: 'viewer',
      object: 'tweet:1',
    },
  ]}
/>

Deleting the previous tuple converts this `tweet` to private:

<WriteRequestViewer
  deleteRelationshipTuples={[
    {
      user: 'user:*',
      relation: 'viewer',
      object: 'tweet:1',
    },
  ]}
/>

By removing the tuple, we made the tweet visible to no-one, which may not be what we want.

<details>
<summary>Limitations on duplicate tuples in a single request</summary>

<br/>
When using the Write API, you cannot include the same tuple (same user, relation, and object) in both the writes and deletes arrays within a single request. The API will return an error with code `cannot_allow_duplicate_tuples_in_one_request` if duplicate tuples are detected.

For example, this request would fail:

```bash
curl -X POST 'http://localhost:8080/stores/{store_id}/write' \
  -H 'content-type: application/json' \
  --data '{
    "writes": {
      "tuple_keys": [{
        "user": "user:anne",
        "relation": "member",
        "object": "group:2"
      }]
    },
    "deletes": {
      "tuple_keys": [{
        "user": "user:anne",
        "relation": "member",
        "object": "group:2"
      }]
    }
  }'
```

</details>

The Write API allows you to send up to 100 unique tuples in the request. (This limit applies to the sum of both writes and deletes in that request). This means we can submit one API call that converts the `tweet` from public to visible to only the `user`'s followers.

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: "Anne's followers can view tweet:1",
      user: 'user:anne#follower',
      relation: 'viewer',
      object: 'tweet:1',
    },
  ]}
  deleteRelationshipTuples={[
    {
      _description: 'tweet:1 is no longer viewable by everyone (*)',
      user: 'user:*',
      relation: 'viewer',
      object: 'tweet:1',
    },
  ]}
  allowedLanguages={[
    SupportedLanguage.JS_SDK,
    SupportedLanguage.GO_SDK,
    SupportedLanguage.DOTNET_SDK,
    SupportedLanguage.PYTHON_SDK,
    SupportedLanguage.JAVA_SDK,
    SupportedLanguage.CURL,
    SupportedLanguage.RPC,
  ]}
/>

##### 02. Add multiple related relationship tuples in the same transaction

Sending multiple tuples per request can also help maintain consistency. For example, if `anne` follows `becky`, you can save the following two tuples or neither of them:

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      _description: 'Anne is a follower of Becky',
      user: 'user:anne',
      relation: 'follower',
      object: 'user:becky',
    },
    {
      _description: 'Becky is followed by Anne',
      user: 'user:becky',
      relation: 'followed_by',
      object: 'user:anne',
    },
  ]}
/>

:::info
In this case, the type `user` exists because users can be related to each other, so users now are a type in the system.
:::

The <ProductName format={ProductNameFormat.LongForm}/> service attempts to perform all the changes sent in a single Write API call in one transaction. If it cannot complete all the changes, it rejects all of them.

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to update tuples."
  relatedLinks={[
    {
      title: 'Update relationship tuples in SDK',
      description: 'Learn about how to update relationship tuples in SDK.',
      link: '../getting-started/update-tuples',
      id: '../getting-started/update-tuples',
    },
    {
      title: '{ProductName} API',
      description: 'Details on the write API in the {ProductName} reference guide.',
      link: '/api/service#Relationship%20Tuples/Write',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/interacting/transactional-writes.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/advanced/entitlements.mdx -->

---
title: Entitlements
description: Modeling entitlements for a system
sidebar_position: 1
slug: /modeling/advanced/entitlements
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  Playground,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  WriteRequestViewer,
} from '@components/Docs';

### Modeling Entitlements for a System with <ProductName format={ProductNameFormat.ShortForm}/>

<DocumentationNotice />

This tutorial explains how to model entitlements for a platform like GitHub using <ProductName format={ProductNameFormat.ShortForm}/>.

<CardBox title="What you will learn">

- How to model an entitlement use case in <ProductName format={ProductNameFormat.ProductLink}/>
- How to start with a given set of requirements and scenarios and iterate on the <ProductName format={ProductNameFormat.ShortForm}/> model until those requirements are met

</CardBox>

<Playground title="Entitlements" preset="entitlements" example="Entitlements" store="entitlements" />

#### Before you start

In order to understand this guide correctly you must be familiar with some <ProductName format={ProductNameFormat.LongForm}/> concepts and know how to develop the things that we will list below.

<details>
<summary>

##### <ProductName format={ProductNameFormat.ShortForm}/> concepts

It would be helpful to have an understanding of some concepts of <ProductName format={ProductNameFormat.ShortForm}/> before you start.

</summary>

###### Modeling object-to-object relationships

You need to know how to create relationships between objects and how that might affect a user's relationships to those objects. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../building-blocks/object-to-object-relationships.mdx)

Used here to indicate that members of an org are subscriber members of the plan the org is subscriber to, and subscriber members of a plan get access to all the plan's features.

###### Direct relationships

You need to know how to disallow granting direct relation to an object and requiring the user to have a relation with another object that would imply a relation with the first one. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../building-blocks/direct-relationships.mdx)

Used here to indicate that "access" to a feature cannot be directly granted to a user, but is implied through the users organization subscribing to a plan that offers that feature.

###### Concepts & configuration language

- Some <ProductConcept />
- [Configuration Language](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx)

</details>

#### What you will be modeling

In many product offerings, the features are behind multiple tiers. In this tutorial, you will build an authorization model for a subset of [GitHub's entitlements](https://github.com/pricing) (detailed below) using <ProductName format={ProductNameFormat.LongForm}/>. You will use some scenarios to validate the model.

![GitHub Pricing Plan](https://github.com/openfga/openfga.dev/blob/main/./assets/entitlements-image-pricing-github.svg)

At their core, entitlements is just asking: does a user X have access to feature Y? In GitHub's case for example, they have a concept called "Draft Pull Requests". Once the user loads the Pull Request page, the frontend needs to know whether it can show the "Draft Pull Request" option, as in it needs to know: "Does the current user have access to feature Draft Pull Request?".

![GitHub PR Page with Draft Pull Request](https://github.com/openfga/openfga.dev/blob/main/./assets/entitlements-image-github-draft-pr.svg)
![GitHub PR Page without Draft Pull Request](https://github.com/openfga/openfga.dev/blob/main/./assets/entitlements-image-github-no-draft-pr.svg)

> Note: For brevity, this tutorial will not model all of GitHub entitlements. Instead, it will focus on modeling for the scenarios outlined below

##### Requirements

You will model an entitlement system similar to GitHub's, focusing on a few scenarios.

GitHub has 3 plans: "Free", "Team" and "Enterprise", with each of them offering several features. The higher-priced plans include all the features of the lower priced plans. You will be focusing on a subset of the features offered.

A summary of GitHub's entitlement system:

- Free
  - Issues
- Team
  - _Everything from the free plan_
  - Draft Pull Requests
- Enterprise
  - _Everything from the team plan_
  - SAML Single Sign-On

##### Defined scenarios

Use the following scenarios to be able to validate whether the model of the requirements is correct.

- Take these three organizations

  - Alpha Beta Gamma (`alpha`), a **subscriber** on the **free** plan
  - Bayer Water Supplies (`bayer`), a **subscriber** on the **team** plan
  - Cups and Dishes (`cups`), a **subscriber** on the **enterprise** plan

- Take these three users
  - **Anne**, **member** of **Alpha Beta Gamma**
  - **Beth**, **member** of **Bayer Water Supplies**
  - **Charles**, **member** of **Cups and Dishes**

![Image showing requirements](https://github.com/openfga/openfga.dev/blob/main/./assets/entitlements-requirements.svg)

By the end of this tutorial, you should be able to query <ProductName format={ProductNameFormat.ShortForm}/> with queries like:

- **Anne** has access to **Issues** (expecting `yes`)
- **Anne** has access to **Draft Pull Requests** (expecting` no`)
- **Anne** has access to **Single Sign-on** (expecting` no`)
- **Beth** has access to **Issues** (expecting `yes`)
- **Beth** has access to **Draft Pull Requests** (expecting `yes`)
- **Beth** has access to **Single Sign-on** (expecting` no`)
- **Charles** has access to **Issues** (expecting `yes`)
- **Charles** has access to **Draft Pull Requests** (expecting `yes`)
- **Charles** has access to **Single Sign-on** (expecting `yes`)

#### Modeling entitlements for GitHub

##### 01. Building The Initial Authorization Model And Relationship Tuples

In this tutorial you are going to take a different approach to previous tutorials. You will start with a simple <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />, add <ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuples" /> to represent some sample scenarios, and iterate until those scenarios return the results you expect.

In the scenarios outlined above, you have `organizations`, `plans` and `features`.

Similar to the example above, start with a basic listing of the types and their relations:

- A `feature` has a `plan` associated to it, we'll call the relation between them `associated_plan`
- A `plan` has an organization as a `subscriber` to it
- An `organization` has users as `members`

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'feature',
        relations: {
          associated_plan: {
            this: {},
          },
        },
        metadata: {
          relations: {
            associated_plan: { directly_related_user_types: [{ type: 'plan' }] },
          },
        },
      },
      {
        type: 'plan',
        relations: {
          subscriber: {
            this: {},
          },
        },
        metadata: {
          relations: {
            subscriber: { directly_related_user_types: [{ type: 'organization' }] },
          },
        },
      },
      {
        type: 'organization',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

##### 02. Populating the relationship tuples

Now you can add the relationship tuples to represent these relationships mentioned in the [requirements](#requirements) and [scenarios](#defined-scenarios) sections:

The relations between the features and plans are as follows:

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'the free plan is the associated plan of the issues feature',
      user: 'plan:free', // the free plan
      relation: 'associated_plan', // is the associated plan
      object: 'feature:issues', // of the issues feature
    },
    {
      _description: 'the team plan is the associated plan of the issues feature',
      user: 'plan:team', // the team plan
      relation: 'associated_plan', // is the associated plan
      object: 'feature:issues', // of the issues feature
    },
    {
      _description: 'the team plan is the associated plan of the draft pull requests feature',
      user: 'plan:team', // the team plan
      relation: 'associated_plan', // is the associated plan
      object: 'feature:draft_prs', // of the draft pull requests feature
    },
    {
      _description: 'the enterprise plan is the associated plan of the issues feature',
      user: 'plan:enterprise', // the enterprise plan
      relation: 'associated_plan', // is the associated plan
      object: 'feature:issues', // of the issues feature
    },
    {
      _description: 'the enterprise plan is the associated plan of the draft pull requests feature',
      user: 'plan:enterprise', // the enterprise plan
      relation: 'associated_plan', // is the associated plan
      object: 'feature:draft_prs', // of the draft pull requests feature
    },
    {
      _description: 'the enterprise plan is the associated plan of the SAML Single Sign-on feature',
      user: 'plan:enterprise', // the enterprise plan
      relation: 'associated_plan', // is the associated plan
      object: 'feature:sso', // of the SAML Single Sign-on feature
    },
  ]}
/>

The relations between the plans and the organizations are as follows:

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'the Alpha Beta Gamma organization is a subscriber of the free plan',
      user: 'organization:alpha', // the Alpha Beta Gamma organization
      relation: 'subscriber', // is a subscriber
      object: 'plan:free', // of the free plan
    },
    {
      _description: 'the Bayer Water Supplies organization is a subscriber of the team plan',
      user: 'organization:bayer', // the Bayer Water Supplies organization
      relation: 'subscriber', // is a subscriber
      object: 'plan:team', // of the team plan
    },
    {
      _description: 'the Cups and Dishes organization is a subscriber of the enterprise plan',
      user: 'organization:cups', // the Cups and Dishes organization
      relation: 'subscriber', // is a subscriber
      object: 'plan:enterprise', // of the enterprise plan
    },
  ]}
/>

The relations between the organizations and the users are as follows:

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'anne is a member of the Alpha Beta Gamma organization',
      user: 'user:anne', // Anne
      relation: 'member', // is a member
      object: 'organization:alpha', // of the Alpha Beta Gamma organization
    },
    {
      _description: 'beth is a member of the Bayer Water Supplies',
      user: 'user:beth', // Beth
      relation: 'member', // is a member
      object: 'organization:bayer', // of the Bayer Water Supplies
    },
    {
      _description: 'charles is a member of the Cups and Dishes organization',
      user: 'user:charles', // Charles
      relation: 'member', // is a member
      object: 'organization:cups', // of the Cups and Dishes organization
    },
  ]}
/>

So far you have given <ProductName format={ProductNameFormat.ShortForm}/> a representation of the current state of your system's relationships. You will keep iterating and updating the authorization model until the results of the queries match what you expect.

:::caution

In production, it is highly recommended to use unique, immutable identifiers. Names are used in this article to make it easier to read and follow.
For example, the relationship tuple indicating that _anne is a member of organization:alpha_ could be written as:

- user: user:2b4840f2-7c9c-42c8-9329-911002051524
- relation: member
- object: project:52e529c6-c571-4d5c-b78a-bc574cf98b54

:::

###### Verification

Now that you have some data, you can start using it to ask is $\{USER\} related to $\{OBJECT\} as $\{RELATION\}?

First, you will <ProductConcept section="what-is-a-check-request" linkName="check" /> if `anne` is a member of `organization:alpha`. This is one of the relationship tuples you previously added, you will make sure <ProductName format={ProductNameFormat.ShortForm}/> can detect a relation in this case.

<CheckRequestViewer user={'user:anne'} relation={'member'} object={'organization:alpha'} allowed={true} />

Querying for relationship tuples that you fed into <ProductName format={ProductNameFormat.LongForm}/> earlier should work, try a few before proceeding to make sure everything is working well.

<CheckRequestViewer user={'user:anne'} relation={'member'} object={'organization:bayer'} allowed={false} />
<CheckRequestViewer user={'organization:bayer'} relation={'subscriber'} object={'plan:team'} allowed={true} />
<CheckRequestViewer user={'plan:free'} relation={'associated_plan'} object={'feature:issues'} allowed={true} />

##### 03. Updating the authorization model

You are working towards <ProductName format={ProductNameFormat.ShortForm}/> returning the correct answer when you query whether `anne` has `access` to `feature:issues`. It won't work yet, but you will keep updating your configuration to reach that goal.

To start, try to run that query on `is anne related to feature:issues as access?`

<CheckRequestViewer user={'user:anne'} relation={'access'} object={'feature:issues'} />

The <ProductName format={ProductNameFormat.LongForm}/> service is returning that the query tuple is invalid. That is because you are asking for relation as `access`, but that relation is not in the configuration of the `feature` type!

Add it now. Like so:

<AuthzModelSnippetViewer
  configuration={{
    type: 'feature',
    relations: {
      associated_plan: {
        this: {},
      },
      access: {
        this: {},
      },
    },
    metadata: {
      relations: {
        associated_plan: { directly_related_user_types: [{ type: 'plan' }] },
        access: { directly_related_user_types: [{ type: 'user' }] },
      },
    },
  }}
  skipVersion={true}
/>

:::info

`access` <ProductConcept section="what-is-a-relation" linkName="relation" /> was added to the configuration of the `feature` <ProductConcept section="what-is-a-type" linkName="type" />.

:::

:::note

In this tutorial, you will find the phrases <ProductConcept section="what-are-direct-and-implied-relationships" linkName="direct relationship and implied relationship" />.

A _direct relationship_ R between user X and object Y means the relationship tuple (user=X, relation=R, object=Y) exists, and the <ProductName format={ProductNameFormat.ShortForm}/> authorization model for that relation allows this direct relationship (by use of [direct relationship type restrictions](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx#direct-relationship-type-restrictions)).

An _implied relationship_ R exists between user X and object Y if user X is related to an object Z that is in direct or implied relationship with object Y, and the <ProductName format={ProductNameFormat.ShortForm}/> authorization model allows it.

:::

The resulting updated configuration would be:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'feature',
        relations: {
          associated_plan: {
            this: {},
          },
          access: {
            this: {},
          },
        },
        metadata: {
          relations: {
            associated_plan: { directly_related_user_types: [{ type: 'plan' }] },
            access: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'plan',
        relations: {
          subscriber: {
            this: {},
          },
        },
        metadata: {
          relations: {
            subscriber: { directly_related_user_types: [{ type: 'organization' }] },
          },
        },
      },
      {
        type: 'organization',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            subscriber: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

###### Adding modeling pattern of parent-child objects

Now we can ask the following query: `is anne related to feature:issues as access?` again.

<CheckRequestViewer user={'user:anne'} relation={'access'} object={'feature:issues'} allowed={false} />

So far so good. <ProductName format={ProductNameFormat.ShortForm}/> understood your query, but said that no <ProductConcept section="what-is-a-relation" linkName="relation" /> exists. That is because according to the configuration provided so far, there is no `access` relation between `anne` and `feature:issues`.

We can also try to query `is organization:alpha related to feature:issues as access?` and we see that there is no relationship.

<CheckRequestViewer user={'organization:alpha'} relation={'access'} object={'feature:issues'} allowed={false} />

If you have already completed some of the other tutorials you might have encountered the modeling pattern of [parent-child objects](https://github.com/openfga/openfga.dev/blob/main/../parent-child.mdx) which is modeled as such:

<AuthzModelSnippetViewer
  configuration={{
    type: 'resource',
    relations: {
      viewer: {
        tupleToUserset: {
          tupleset: {
            relation: 'parent',
          },
          computedUserset: {
            relation: 'all_objects_viewer',
          },
        },
      },
    },
  }}
  skipVersion={true}
/>

:::info
With this, when asked to check a user's `viewer` relationship with the object, <ProductName format={ProductNameFormat.LongForm}/> will:

1. Read all relationship tuples of users related to this particular object as relation `parent`
2. For each relationship tuple, return all _usersets_ that have `all_objects_viewer` relation to the objects in those relationship tuples
3. If the user is in any of those _usersets_, return yes, as the user is a `viewer` on this object.
   In other words, users related as `all_objects_viewer` to any of this object's `parents` are related as `viewer` to this object.

:::

If you want to give all subscribers on a plan access to a feature, you can do it like so:

<AuthzModelSnippetViewer
  configuration={{
    type: 'feature',
    relations: {
      associated_plan: {
        this: {},
      },
      access: {
        union: {
          child: [
            {
              this: {},
            },
            {
              tupleToUserset: {
                tupleset: {
                  relation: 'associated_plan',
                },
                computedUserset: {
                  relation: 'subscriber',
                },
              },
            },
          ],
        },
      },
    },
    metadata: {
      relations: {
        associated_plan: { directly_related_user_types: [{ type: 'plan' }] },
        access: {
          directly_related_user_types: [{ type: 'user' }],
        },
      },
    },
  }}
  skipVersion={true}
/>

:::info
Users related to `feature` as `access` are the union of (any of):

- the set of users with a direct `access` relation
- the set of users related to the `associated_plan` as `subscriber` (the feature's associated plans' subscribers)

So everyone who has direct access, as well as the subscribers of the associated plan
:::

That would mean that in order for an object to have an `access` relation to a feature y, there needs to be either:

- a <ProductConcept section="what-are-direct-and-implied-relationships" linkName="direct relationship" /> via a relationship tuple:
  e.g. `{ "user": "user:x", "relation": "access", "object": "feature:y" }`
- a subscriber relationship with another object related to x associated_plan:
  e.g. `{ "user": "user:x", "relation": "subscriber", "object": "plan:z" } { "user": "plan:z", "relation": "associated_plan", "object": "feature:y" }`

That brings you close. That will allow you to grant organizations access to the feature (as organizations have a subscriber relation with the plan).

###### Adding Subscriber Relationship With Another Object Related To x associated_plan

One way forward would be to add a direct `access` relation between a user and a feature e.g. `{ "user": "anne", "relation": "access", "object": "feature:y" }` whenever the organization anne is subscribed to a plan, or the organization anne is in subscribes to a new plan.
But there are several downsides to this:

- Your application layer now needs to worry about computing this relationship. Instead of letting <ProductName format={ProductNameFormat.ShortForm}/> figure this all out, the app layer needs to do the checks whenever a user is being added or removed
- If an organization changes its subscription, your application layer has to loop through all the users and update their `access` relationships to features accordingly

Later in this tutorial, you will remove the possibility of having a direct `access` relation completely, but for now you will make sure the changes to the store you have made so far are working.

Replace all the existing code you had previously with the updated authorization model from the below snippet.

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'feature',
        relations: {
          associated_plan: {
            this: {},
          },
          access: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      relation: 'associated_plan',
                    },
                    computedUserset: {
                      relation: 'subscriber',
                    },
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            associated_plan: { directly_related_user_types: [{ type: 'plan' }] },
            access: {
              directly_related_user_types: [{ type: 'user' }],
            },
          },
        },
      },
      {
        type: 'plan',
        relations: {
          subscriber: {
            this: {},
          },
        },
        metadata: {
          relations: {
            subscriber: { directly_related_user_types: [{ type: 'organization' }] },
          },
        },
      },
      {
        type: 'organization',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

Now we can ask following query: `is organization:alpha related to feature:issues as access?` again.

<CheckRequestViewer user={'organization:alpha'} relation={'access'} object={'feature:issues'} allowed={true} />

You will notice that <ProductName format={ProductNameFormat.ShortForm}/> now did find a relation, as `organization:alpha` is a `subscriber` to `plan:free` which has an `associated_plan` relation to `feature:issues`. From that and the authorization model you updated above, <ProductName format={ProductNameFormat.ShortForm}/> deduced that `organization:alpha` has an implied `access` relation to `feature:issues`.

That is good, but you want to be able to ask `is anne related to feature:issues as access?`, not `is organization:alpha related to feature:issues as access?`. As in, you want the subscriber members to have access to the feature, not the subscriber itself.

In order to do that, you will add a relation on the plan, that indicates that all members of an organization subscribed to it, have a `subscriber_member` relation to the plan. And you can modify the change you did above to give implied access to the `subscriber_member` instead of the subscriber. Like so:

<AuthzModelSnippetViewer
  description={`Notice that \`subscriber\` has been updated to \`subscriber_member\` in the \`access\` relation of the \`feature\` type.
  Under the \`plan\` type, in order for someone to have a \`subscriber_member\` relation to the plan, they have to be related as a \`member\` to the object related as a \`subscriber\` to the plan (as in they have to be a member of on of the plan's subscribers).`}
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'feature',
        relations: {
          associated_plan: {
            this: {},
          },
          access: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      relation: 'associated_plan',
                    },
                    computedUserset: {
                      relation: 'subscriber_member', // this has been updated from `subscriber` to `subscriber_member`
                    },
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            associated_plan: { directly_related_user_types: [{ type: 'plan' }] },
            access: {
              directly_related_user_types: [{ type: 'user' }],
            },
          },
        },
      },
      {
        type: 'plan',
        relations: {
          subscriber: {
            this: {},
          },
          subscriber_member: {
            // in order for someone to have a `subscriber_member` relation to the plan, they have to
            tupleToUserset: {
              tupleset: {
                // be related to the object related as a `subscriber` to the plan
                relation: 'subscriber',
              },
              computedUserset: {
                // as a `member`
                relation: 'member',
              },
            },
          },
        },
        metadata: {
          relations: {
            subscriber: { directly_related_user_types: [{ type: 'organization' }] },
          },
        },
      },
      {
        type: 'organization',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

:::info

Notice that `subscriber` has been updated to `subscriber_member` in the `access` relation of the `feature` type.

Under the `plan` type, in order for someone to have a `subscriber_member` relation to the plan, they have to be related as a `member` to the object related as a `subscriber` to the plan (as in they have to be a member of on of the plan's subscribers).

:::

Now ask the following query: `is anne related to feature:issues as access?`

<CheckRequestViewer user={'user:anne'} relation={'access'} object={'feature:issues'} allowed={true} />

###### Disallow direct relationship

So far, with just a <ProductName format={ProductNameFormat.ShortForm}/> authorization model, and the initial relationship tuples indicating the relations you know, you configured <ProductName format={ProductNameFormat.LongForm}/> to give you the correct response.

Earlier on, the idea of not allowing a direct `access` relation between a user and a `feature` was discussed, e.g. adding a relationship tuple like `{ "user": "user:anne", "relation": "access", "object": "feature:y" }`. You will remove it now.

To disallow a direct relationship, you need to remove the direct relationship type restriction. The following snippet:

<AuthzModelSnippetViewer
  configuration={{
    type: 'feature',
    relations: {
      associated_plan: {
        this: {},
      },
      access: {
        union: {
          child: [
            {
              this: {},
            },
            {
              tupleToUserset: {
                tupleset: {
                  relation: 'associated_plan',
                },
                computedUserset: {
                  relation: 'subscriber_member',
                },
              },
            },
          ],
        },
      },
    },
    metadata: {
      relations: {
        associated_plan: { directly_related_user_types: [{ type: 'plan' }] },
        access: {
          directly_related_user_types: [{ type: 'user' }],
        },
      },
    },
  }}
  skipVersion={true}
/>

becomes

<AuthzModelSnippetViewer
  configuration={{
    type: 'feature',
    relations: {
      associated_plan: {
        this: {},
      },
      access: {
        tupleToUserset: {
          tupleset: {
            relation: 'associated_plan',
          },
          computedUserset: {
            relation: 'subscriber_member',
          },
        },
      },
    },
    metadata: {
      relations: {
        associated_plan: { directly_related_user_types: [{ type: 'plan' }] },
      },
    },
  }}
  skipVersion={true}
/>

With this change, even if your app layer added the following relationship tuple:

- `{ "user": "user:anne", "relation": "access", "object": feature:issues }`

a subsequent check for `is anne related to feature:issues as access?` would return no relation. The only way for a relation to exist is if the following three relationship tuples do:

- `{ "user": "user:anne", "relation": "member", "object": "organization:z" }`
- `{ "user": "organization:z", "relation": "subscriber", "object": "plan:y" }`
- `{ "user": "plan:y", "relation": "associated_plan", "object": "feature:issues" }`

###### Verification

Ensure that your authorization model matches the one below

<AuthzModelSnippetViewer
  configuration={{
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'feature',
        relations: {
          associated_plan: {
            this: {},
          },
          access: {
            tupleToUserset: {
              tupleset: {
                relation: 'associated_plan',
              },
              computedUserset: {
                relation: 'subscriber_member',
              },
            },
          },
        },
        metadata: {
          relations: {
            associated_plan: { directly_related_user_types: [{ type: 'plan' }] },
          },
        },
      },
      {
        type: 'plan',
        relations: {
          subscriber: {
            this: {},
          },
          subscriber_member: {
            tupleToUserset: {
              tupleset: {
                relation: 'subscriber',
              },
              computedUserset: {
                relation: 'member',
              },
            },
          },
        },
        metadata: {
          relations: {
            subscriber: { directly_related_user_types: [{ type: 'organization' }] },
          },
        },
      },
      {
        type: 'organization',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

You will now verify that the configuration is correct by running checks for all the scenarios mentioned at the beginning of the tutorial:

- **Anne** has access to **Issues** (expecting `yes`)
- **Anne** has access to **Draft Pull Requests** (expecting` no`)
- **Anne** has access to **Single Sign-on** (expecting` no`)
- **Beth** has access to **Issues** (expecting `yes`)
- **Beth** has access to **Draft Pull Requests** (expecting `yes`)
- **Beth** has access to **Single Sign-on** (expecting` no`)
- **Charles** has access to **Issues** (expecting `yes`)
- **Charles** has access to **Draft Pull Requests** (expecting `yes`)
- **Charles** has access to **Single Sign-on** (expecting `yes`)

<CheckRequestViewer user={'user:anne'} relation={'access'} object={'feature:issues'} allowed={true} />

Try to verify for the other user, object and relation combinations as listed below.

| User      | Object              | Relation | Query                                                | Relation? |
| --------- | ------------------- | -------- | ---------------------------------------------------- | --------- |
| `anne`    | `feature:issues`    | `access` | `is anne related to feature:issues as access?`       | Yes       |
| `anne`    | `feature:draft_prs` | `access` | `is anne related to feature:draft_prs as access?`    | No        |
| `anne`    | `feature:sso`       | `access` | `is anne related to feature:sso as access?`          | No        |
| `beth`    | `feature:issues`    | `access` | `is beth related to feature:issues as access?`       | Yes       |
| `beth`    | `feature:draft_prs` | `access` | `is beth related to feature:draft_prs as access?`    | Yes       |
| `beth`    | `feature:sso`       | `access` | `is beth related to feature:sso as access?`          | No        |
| `charles` | `feature:issues`    | `access` | `is charles related to feature:issues as access?`    | Yes       |
| `charles` | `feature:draft_prs` | `access` | `is charles related to feature:draft_prs as access?` | Yes       |
| `charles` | `feature:sso`       | `access` | `is charles related to feature:sso as access?`       | Yes       |

#### Summary

In this tutorial, you learned:

- to model entitlements for a system in <ProductName format={ProductNameFormat.LongForm}/>
- how to start with a set of requirements and scenarios and iterate on the <ProductName format={ProductNameFormat.ShortForm}/> authorization model until the checks match the expected scenarios
- how to model [**parent-child relationships**](https://github.com/openfga/openfga.dev/blob/main/../parent-child.mdx) to indicate that a user having a relationship with a certain object implies having a relationship with another object in <ProductName format={ProductNameFormat.ShortForm}/>
- how to use [**the union operator**](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx#the-union-operator) condition to indicate multiple possible paths for a relationship between two objects to be computed
- using [**direct relationship type restrictions**](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx#direct-relationship-type-restrictions) in a <ProductName format={ProductNameFormat.ShortForm}/> authorization model, and how to block direct relationships by removing it

<Playground title="Entitlements" preset="entitlements" example="Entitlements" store="entitlements" />

Upcoming tutorials will dive deeper into <ProductName format={ProductNameFormat.ShortForm}/>, introducing concepts that will improve on the model you built today, and tackling different permission systems, with other relations and requirements that need to be met.


<!-- End of openfga/openfga.dev/docs/content/modeling/advanced/entitlements.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/advanced/gdrive.mdx -->

---
title: Google Drive
description: Modeling Google Drive permissions
sidebar_position: 1
slug: /modeling/advanced/gdrive
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  Playground,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  WriteRequestViewer,
} from '@components/Docs';

### Modeling Google Drive permissions with <ProductName format={ProductNameFormat.ShortForm}/>

<DocumentationNotice />

This tutorial explains how to represent [Google Drive](https://www.google.com/intl/en-GB/drive/) permissions model with <ProductName format={ProductNameFormat.ProductLink}/>.

<CardBox title="What you will learn">

- Indicate <ProductConcept section="what-is-a-relationship" linkName="relationships" /> between a group of **<ProductConcept section="what-is-a-user" linkName="users" />** and an **<ProductConcept section="what-is-an-object" linkName="object" />**. See [Modeling User Groups](https://github.com/openfga/openfga.dev/blob/main/../user-groups.mdx) for more.<br />
  Used here to indicate that all users within a domain can access a document (sharing a document within an organization).
- Model **concentric relationship** to have a certain <ProductConcept section="what-is-a-relation" linkName="relation" /> on an object imply another relation on the same object. See [Modeling Concepts: Concentric Relationships](https://github.com/openfga/openfga.dev/blob/main/../building-blocks/concentric-relationships.mdx) for more.<br />
  Used here is to indicate that writers are also commenters and viewers.
- Using [**the union operator**](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx#the-union-operator) condition to indicate that a user might have a certain relation with an object if they match any of the criteria indicated.<br />
  Used here to indicate that a user can be a viewer on a document, or can have the viewer relationship implied through commenter.
- Using the **<ProductConcept section="what-is-type-bound-public-access" linkName="type bound public access" />** in a <ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuple's" /> user field to indicate that everyone has a certain relation with an object. See [Modeling Public Access](https://github.com/openfga/openfga.dev/blob/main/../public-access.mdx) for more.<br />
  Used here to [share documents publicly](#04-sharing-files-and-folders-publicly).
- Model [**parent-child objects**](https://github.com/openfga/openfga.dev/blob/main/../parent-child.mdx) to indicate that a user having a relationship with a certain object implies having a relationship with another object in <ProductName format={ProductNameFormat.ShortForm}/>.<br />
  Used here is to indicate that a writer on a folder is a writer on all documents inside that folder.

</CardBox>

<Playground title="Google Drive" preset="drive" example="Google Drive" store="gdrive" />

#### Before you start

In order to understand this guide correctly you must be familiar with some <ProductName format={ProductNameFormat.LongForm}/> concepts and know how to develop the things that we will list below.

<details>
<summary>

##### <ProductName format={ProductNameFormat.ShortForm}/> concepts

It would be helpful to have an understanding of some concepts of <ProductName format={ProductNameFormat.ShortForm}/> before you start.

</summary>

###### Modeling concentric relationships

You need to know how to update the authorization model to allow having nested relations such as all writers are readers. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../building-blocks/concentric-relationships.mdx)

###### Modeling object-to-object relationships

You need to know how to create relationships between objects and how that might affect a user's relationships to those objects. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../building-blocks/object-to-object-relationships.mdx)

Used here to indicate that users who have access to view a folder have access to view all documents inside it.

###### Modeling public access

You need to know how to add a relationship tuple to indicate that a resource is publicly available. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../public-access.mdx)

###### Concepts & configuration language

- The <ProductConcept />
- [Configuration Language](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx)

</details>

#### What you will be modeling

Google Drive is a system to store, share, and collaborate on files and folders. [Source](https://www.google.com/drive/)

In this tutorial, you will build a subset of the Google Drive permission model (detailed below) in <ProductName format={ProductNameFormat.LongForm}/>, using some scenarios to validate the model.

> Note: For brevity, this tutorial will not model all of Google Drive's permissions. Instead, it will focus on modeling for the scenarios outlined below

##### Requirements

Google Drive's permission model is represented in [their documentation](https://developers.google.com/drive/api/v3/ref-roles).

In this tutorial, you will be focusing on a subset of these permissions.

Requirements:

- Users can be owners, editors, commenters and viewers of documents
- Documents can be shared with all users in a domain
- Folders can contain documents and users with a certain permission on a folder have that same permission to a document in that folder
- Documents and folders can be shared publicly

##### Defined scenarios

There will be the following users:

- Anne, who is in the xyz domain
- Beth, who is in the xyz domain
- Charles, who is in the xyz domain
- Diane, who is NOT in the xyz domain
- Erik, who is NOT in the xyz domain

There will be:

- a 2021-budget document, owned by Anne, shared for commenting with Beth and viewable by all members of the xyz domain.
- a 2021-planning folder, viewable by Diane and contains the 2021-budget document
- a 2021-public-roadmap document, owned by Anne, available for members xyz domain to comment on and is publicly viewable

#### Modeling Google Drive's permissions

##### 01. Individual permissions

To keep thing simple and focus on <ProductName format={ProductNameFormat.LongForm}/> features rather than Google Drive complexity we will model only four [roles](https://developers.google.com/drive/api/v3/ref-roles) (Viewer, Commenter, Writer, Owner).

At the end of this section we want to have the following permissions represented:

![Image showing permissions](https://github.com/openfga/openfga.dev/blob/main/./assets/gdrive-gdrive1.svg)

To represent permissions in <ProductName format={ProductNameFormat.ShortForm}/> we use <ProductConcept section="what-is-a-relation" linkName="relations" />. For document permissions we need to create the following <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          owner: {
            this: {},
          },
          writer: {
            this: {},
          },
          commenter: {
            this: {},
          },
          viewer: {
            this: {},
          },
        },
        metadata: {
          relations: {
            owner: { directly_related_user_types: [{ type: 'user' }] },
            writer: { directly_related_user_types: [{ type: 'user' }] },
            commenter: { directly_related_user_types: [{ type: 'user' }] },
            viewer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

The <ProductName format={ProductNameFormat.LongForm}/> service determines if a <ProductConcept section="what-is-a-user" linkName="user" /> has access to an <ProductConcept section="what-is-an-object" linkName="object" /> by <ProductConcept section="what-is-a-check-request" linkName="checking" /> if the user has a relation to that object. Let us examine one of those relations in detail:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type: 'document', // objects of type document
    relations: {
      // have users related to them as...
      viewer: {
        // "viewer": if those users belong to:
        this: {}, // the userset of all users related to the document as "viewer"
      },
    },
    metadata: {
      relations: {
        viewer: { directly_related_user_types: [{ type: 'user' }] },
      },
    },
  }}
  skipVersion={true}
/>

:::info

The snippet above indicates that objects of type document have users related to them as "viewer" if those users belong to the userset of all users related to the document as "viewer".

This means that a user can be <ProductConcept section="what-are-direct-and-implied-relationships" linkName="directly related" /> as a viewer to an object of type "document"

:::

If we want to say `beth` is a commenter of **document:2021-budget** we create this relationship tuple:

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:beth',
      relation: 'commenter',
      object: 'document:2021-budget',
    },
  ]}
/>

We can now ask <ProductName format={ProductNameFormat.ShortForm}/> "is `beth` a commenter of repository **document:2021-budget**?"

<CheckRequestViewer user={'user:beth'} relation={'commenter'} object={'document:2021-budget'} allowed={true} />

We could also say that `anne` is an owner of the same document:

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:anne',
      relation: 'owner',
      object: 'document:2021-budget',
    },
  ]}
/>

And <ProductConcept section="what-is-a-check-request" linkName="ask" /> some questions to <ProductName format={ProductNameFormat.ShortForm}/>:

<CheckRequestViewer user={'user:anne'} relation={'owner'} object={'document:2021-budget'} allowed={true} />
<CheckRequestViewer user={'user:anne'} relation={'writer'} object={'document:2021-budget'} allowed={false} />

The first reply makes sense but the second one does not. Intuitively, if `anne` was an **owner**, she was also be a **writer**. In fact, Google Drive explains this in [their documentation](https://developers.google.com/drive/api/v3/ref-roles)

![Image showing roles](https://github.com/openfga/openfga.dev/blob/main/./assets/gdrive-roles.svg)

To make <ProductName format={ProductNameFormat.ShortForm}/> aware of this "concentric" permission model we need to update our definitions:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document', // objects of type document
        relations: {
          // have users related to them as
          owner: {
            this: {},
          },
          writer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'owner',
                  },
                },
              ],
            },
          },
          commenter: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'writer',
                  },
                },
              ],
            },
          },
          viewer: {
            // "viewer": if they belong to
            union: {
              // any of (the union of) these user sets
              child: [
                {
                  this: {}, // the userset of all users related to the document as "viewer"
                },
                {
                  computedUserset: {
                    // the userset of all users related to the document as "commenter"
                    relation: 'commenter',
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            owner: { directly_related_user_types: [{ type: 'user' }] },
            writer: { directly_related_user_types: [{ type: 'user' }] },
            commenter: { directly_related_user_types: [{ type: 'user' }] },
            viewer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

:::info

Let's examine one of those relations in detail:

objects of type document have users related to them as "viewer": if they belong to any of (the union of) the following:

- the userset of all users related to the document as "viewer"
- the userset of all users related to the document as "commenter"

:::

With this update our model now supports nested definitions and now:

<CheckRequestViewer user={'user:anne'} relation={'owner'} object={'document:2021-budget'} allowed={true} />
<CheckRequestViewer user={'user:anne'} relation={'writer'} object={'document:2021-budget'} allowed={true} />

##### 02. Organization permissions

Google Drive allows you to share a file with everyone in your organization as a viewer, commenter or writer/editor.

![](https://raw.githubusercontent.com/openfga/openfga.dev/main/./assets/gdrive-org.svg)

At the end of this section we want to end up with the following permissions represented:

![Image showing permissions](https://github.com/openfga/openfga.dev/blob/main/./assets/gdrive-gdrive2.svg)

To add support for domains and members all we need to do is add this object to the <ProductName format={ProductNameFormat.ProductLink}/> <ProductConcept section="what-is-a-type-definition" linkName="authorization model" />. In addition, update the model to allow domain member to be assigned to document:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document', // objects of type document
        relations: {
          // have users related to them as
          owner: {
            this: {},
          },
          writer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'owner',
                  },
                },
              ],
            },
          },
          commenter: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'writer',
                  },
                },
              ],
            },
          },
          viewer: {
            // "viewer": if they belong to
            union: {
              // any of (the union of) these user sets
              child: [
                {
                  this: {}, // the userset of all users related to the document as "viewer"
                },
                {
                  computedUserset: {
                    // the userset of all users related to the document as "commenter"
                    relation: 'commenter',
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            owner: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
            writer: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
            commenter: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
            viewer: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
          },
        },
      },
      {
        type: 'domain',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

:::info

Objects of type "domain" have users related to them as "member" if they belong to the userset of all users related to the domain as "member".

In other words, users can be direct members of a domain.

:::

Let's now create a domain, add members to it and make all members **viewers** of **document:2021-budget**.

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'make anne, beth, charles a member of the xyz domain',
      user: 'user:anne',
      relation: 'member',
      object: 'domain:xyz',
    },
    {
      user: 'user:beth',
      relation: 'member',
      object: 'domain:xyz',
    },
    {
      user: 'user:charles',
      relation: 'member',
      object: 'domain:xyz',
    },
    {
      _description: 'make members of xyz domain viewers of document:2021-budget',
      user: 'domain:xyz#member',
      relation: 'viewer',
      object: 'document:2021-budget',
    },
  ]}
/>

The last relationship tuple introduces a new **<ProductName format={ProductNameFormat.ShortForm}/>** concept. A **<ProductConcept section="what-is-a-user" linkName="userset" />**. When the value of a user is formatted like this **objectType:objectId#relation**, <ProductName format={ProductNameFormat.LongForm}/> will automatically expand the userset into all its individual user identifiers:

<CheckRequestViewer user={'user:charles'} relation={'viewer'} object={'document:2021-budget'} allowed={true} />

##### 03. Folder permission propagation

[Permission propagation](https://developers.google.com/drive/api/v3/manage-sharing#permission_propagation) happens between folders and files: if you are a viewer in a folder, you can view its documents. This applies even when you are not explicitly a viewer in a document.
![Image](https://pbs.twimg.com/media/Eme_FlYW4AEAYfi?format=jpg&name=large)

At the end of this section we want to end up with the following permissions represented. Note that a folder is an object in the **document** type, as we do not need a separate type:

![Image showing permissions](https://github.com/openfga/openfga.dev/blob/main/./assets/gdrive-gdrive3.svg)

We need to add the notion that a **document** can be the **parent** of another **document**. We know how to do that:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          parent: {
            // add this relation
            this: {},
          },
          owner: {
            this: {},
          },
          writer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'owner',
                  },
                },
              ],
            },
          },
          commenter: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'writer',
                  },
                },
              ],
            },
          },
          viewer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'commenter',
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            parent: { directly_related_user_types: [{ type: 'document' }] },
            owner: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
            writer: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
            commenter: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
            viewer: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
          },
        },
      },
    ],
  }}
/>

:::info

Notice the newly added "parent" relation in the configuration above.

:::

We can indicate this relation by adding the following relationship tuples

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'Diane is a viewer of document:2021-planning',
      user: 'user:diane',
      relation: 'viewer',
      object: 'document:2021-planning',
    },
    {
      _description: 'document:2021-planning is a parent of document:2021-budget',
      user: 'document:2021-planning',
      relation: 'parent',
      object: 'document:2021-budget',
    },
  ]}
/>

What we still lack is the ability to propagate permissions from parent to children. We want to say that a user is a viewer of a document if either:

- [done] they have a viewer relationship (directly or through domain membership)
- [pending] they have a viewer relationship with the parent document

We need a way to consider the parent viewers, not just direct viewers of the document when getting a check for:

<CheckRequestViewer user={'user:diane'} relation={'viewer'} object={'document:2021-budget'} />

More details on this technique can be found in the section [Modeling Parent-Child Objects](https://github.com/openfga/openfga.dev/blob/main/../parent-child.mdx).

We express it like this:

<AuthzModelSnippetViewer
  configuration={{
    type: 'document',
    relations: {
      viewer: {
        union: {
          child: [
            {
              this: {},
            },
            {
              computedUserset: {
                relation: 'commenter',
              },
            },
            {
              tupleToUserset: {
                tupleset: {
                  // read all relationship tuples related to document:2021-budget as parent
                  // which returns [{ "object": "document:2021-budget", "relation": "parent", "user": "document:2021-planning"}]
                  relation: 'parent',
                },
                computedUserset: {
                  // and for each relationship tuple return all usersets that match the following, replacing $TUPLE_USERSET_OBJECT with document:2021-planning
                  // this will return relationship tuples of shape { "object": "document:2021-planning", "viewer", "user": ??? }
                  // including { "object": "document:2021-planning", "viewer", "user": "user:diane" }
                  relation: 'viewer',
                },
              },
            },
          ],
        },
      },
    },
    metadata: {
      relations: {
        viewer: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
      },
    },
  }}
  skipVersion={true}
/>

:::info

The users with a viewer relationship to a certain object of type "document" are any of:

- the "viewers": the set of users who are <ProductConcept section="what-are-direct-and-implied-relationships" linkName="directly related" /> to the document as a "viewer"
- the "commenters": the set of users who are related to the object as "commenter"
- the "viewers of the parents": from the objects who are related to the doc as parent, return the sets of users who are related to those objects as "viewer"

What the added section is doing is:

1. read all relationship tuples related to document:2021-budget as parent which returns:

`[{ "object": "document:2021-budget", "relation": "parent", "user": "document:2021-planning" }]`

2. for each relationship tuple read, return all usersets that match the following, returning tuples of shape:

`{ "object": "document:2021-planning", "viewer", "user": ??? }`

including: `{ "object": "document:2021-planning", "viewer", "user": "user:diane" }`

:::

The updated authorization model looks like this:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          owner: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  tupleToUserset: {
                    computedUserset: {
                      relation: 'owner',
                    },
                    tupleset: {
                      relation: 'parent',
                    },
                  },
                },
              ],
            },
          },
          writer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'owner',
                  },
                },
                {
                  tupleToUserset: {
                    computedUserset: {
                      relation: 'writer',
                    },
                    tupleset: {
                      relation: 'parent',
                    },
                  },
                },
              ],
            },
          },
          commenter: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'writer',
                  },
                },
                {
                  tupleToUserset: {
                    computedUserset: {
                      relation: 'commenter',
                    },
                    tupleset: {
                      relation: 'parent',
                    },
                  },
                },
              ],
            },
          },
          viewer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'commenter',
                  },
                },
                {
                  tupleToUserset: {
                    computedUserset: {
                      relation: 'viewer',
                    },
                    tupleset: {
                      relation: 'parent',
                    },
                  },
                },
              ],
            },
          },
          parent: {
            this: {},
          },
        },
        metadata: {
          relations: {
            parent: { directly_related_user_types: [{ type: 'document' }] },
            owner: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
            writer: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
            commenter: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
            viewer: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
          },
        },
      },
      {
        type: 'domain',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

##### 04. Sharing files and folders publicly

Google Drive has a feature which allows [sharing a file or folder publicly](https://support.google.com/a/users/answer/9308873?hl=en), and specifying the permissions a public user might have (writer/commenter/viewer).

Assume that `Anne` has created a new document: `2021-public-roadmap`, has shared it with commenter permissions to the `xyz.com`, and has shared it as view only with the public at large.

![Image showing requirements](https://github.com/openfga/openfga.dev/blob/main/./assets/gdrive-gdrive4.svg)

Here's where another <ProductName format={ProductNameFormat.LongForm}/> feature, <ProductConcept section="what-is-type-bound-public-access" linkName="type bound public access" /> (as in everyone), would come in handy.

First, we will need to update our model to allow for public access with type `user` for viewer relation.

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          owner: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  tupleToUserset: {
                    computedUserset: {
                      relation: 'owner',
                    },
                    tupleset: {
                      relation: 'parent',
                    },
                  },
                },
              ],
            },
          },
          writer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'owner',
                  },
                },
                {
                  tupleToUserset: {
                    computedUserset: {
                      relation: 'writer',
                    },
                    tupleset: {
                      relation: 'parent',
                    },
                  },
                },
              ],
            },
          },
          commenter: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'writer',
                  },
                },
                {
                  tupleToUserset: {
                    computedUserset: {
                      relation: 'commenter',
                    },
                    tupleset: {
                      relation: 'parent',
                    },
                  },
                },
              ],
            },
          },
          viewer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'commenter',
                  },
                },
                {
                  tupleToUserset: {
                    computedUserset: {
                      relation: 'viewer',
                    },
                    tupleset: {
                      relation: 'parent',
                    },
                  },
                },
              ],
            },
          },
          parent: {
            this: {},
          },
        },
        metadata: {
          relations: {
            parent: { directly_related_user_types: [{ type: 'document' }] },
            owner: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
            writer: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
            commenter: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
            viewer: {
              directly_related_user_types: [
                { type: 'user' },
                { type: 'user', wildcard: {} },
                { type: 'domain', relation: 'member' },
              ],
            },
          },
        },
      },
      {
        type: 'domain',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

To mark Anne as the owner, the domain members as commenters and the public as viewers, we need to add the following relationship tuples:

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'Anne is the owner of document:2021-public-roadmap',
      user: 'user:anne',
      relation: 'owner',
      object: 'document:2021-public-roadmap',
    },
    {
      _description: 'Members of the domain:xyz can comment on document:2021-public-roadmap',
      user: 'domain:xyz#member',
      relation: 'commenter',
      object: 'document:2021-public-roadmap',
    },
    {
      _description: 'Everyone with type `user` can view document:2021-public-roadmap',
      user: 'user:*',
      relation: 'viewer',
      object: 'document:2021-public-roadmap',
    },
  ]}
/>

Anne is an owner of the document

<CheckRequestViewer user={'user:anne'} relation={'owner'} object={'document:2021-public-roadmap'} allowed={true} />

Beth is a member of the xyz.com domain, and so can comment but cannot write

<CheckRequestViewer user={'user:beth'} relation={'writer'} object={'document:2021-public-roadmap'} allowed={false} />
<CheckRequestViewer user={'user:beth'} relation={'commenter'} object={'document:2021-public-roadmap'} allowed={true} />

Erik is NOT a member of the xyz.com domain, and so can only view the document

<CheckRequestViewer user={'user:erik'} relation={'writer'} object={'document:2021-public-roadmap'} allowed={false} />
<CheckRequestViewer user={'user:erik'} relation={'viewer'} object={'document:2021-public-roadmap'} allowed={true} />

<Playground title="Google Drive" preset="drive" example="Google Drive" store="gdrive" />

#### Related Sections

<RelatedSection
  description="Take a look at the following sections for more information."
  relatedLinks={[
    {
      title: 'Search with permissions',
      description: 'Give your users search results with objects that they have access to',
      link: '../../interacting/search-with-permissions',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/advanced/gdrive.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/advanced/github.mdx -->

---
title: GitHub
description: Modeling GitHub permissions
sidebar_position: 2
slug: /modeling/advanced/github
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  Playground,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  WriteRequestViewer,
} from '@components/Docs';

### Modeling GitHub permissions with <ProductName format={ProductNameFormat.ShortForm}/>

<DocumentationNotice />

This tutorial explains how to model GitHub's Organization permission model using <ProductName format={ProductNameFormat.ProductLink}/>. [This article](https://docs.github.com/en/free-pro-team@latest/github/setting-up-and-managing-organizations-and-teams/managing-access-to-your-organizations-repositories) from the GitHub docs has links to all other articles we are going to be exploring in this document.

<CardBox title="What you will learn">

- Indicate <ProductConcept section="what-is-a-relationship" linkName="relationships" /> between a group of **<ProductConcept section="what-is-a-user" linkName="users" />** and an **<ProductConcept section="what-is-an-object" linkName="object" />**. See [Modeling User Groups](https://github.com/openfga/openfga.dev/blob/main/../user-groups.mdx) for more details.<br />
  Used here to indicate that all members of an organization are repository admins on the organization.
- Modeling **concentric relationship** to have a certain <ProductConcept section="what-is-a-relation" linkName="relation" /> on an object imply another relation on the same object. See [Modeling Concepts: Concentric Relationships](https://github.com/openfga/openfga.dev/blob/main/../building-blocks/concentric-relationships.mdx) for more.<br />
  Used here to indicate that maintainers of a repository are also writers of that repository.
- Using [**the union operator**](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx#the-union-operator) condition to indicate that a user might have a certain relation with an object if they match any of the criteria indicated.<br />
  Used here to indicate that a user can be a reader on a repository, or can have the reader relationship implied through triager.
- Model [**parent-child objects**](https://github.com/openfga/openfga.dev/blob/main/../parent-child.mdx) to indicate that a user having a relationship with a certain object implies having a relationship with another object in <ProductName format={ProductNameFormat.ShortForm}/>.<br />
  Used here to indicate that a repository admin on a GitHub organization, is an admin on all repositories that organization owns.

</CardBox>

<Playground title="GitHub" preset="github" example="GitHub" store="github" />

#### Before you start

In order to understand this guide correctly you must be familiar with some <ProductName format={ProductNameFormat.LongForm}/> concepts and know how to develop the things that we will list below.

<details>
<summary>

##### <ProductName format={ProductNameFormat.ShortForm}/> concepts

It would be helpful to have an understanding of some concepts of <ProductName format={ProductNameFormat.ShortForm}/> before you start.

</summary>

###### Modeling concentric relationships

You need to know how to update the authorization model to allow having nested relations such as all writers are readers. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../building-blocks/concentric-relationships.mdx)

###### Modeling object-to-object relationships

You need to know how to create relationships between objects and how that might affect a user's relationships to those objects. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../building-blocks/object-to-object-relationships.mdx)

Used here to indicate that users who have repo admin access on an organization, have admin access to all repositories owned by that organization.

###### Concepts & configuration language

- Some <ProductConcept />
- [Configuration Language](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx)

</details>

#### What you will be modeling

GitHub is a system to develop and collaborate on code.

In this tutorial, you will build a subset of the GitHub permission model (detailed below) in <ProductName format={ProductNameFormat.LongForm}/>, using some scenarios to validate the model.

> Note: For brevity, this tutorial will not model all of GitHub's permissions. Instead, it will focus on modeling for the scenarios outlined below

##### Requirements

GitHub's permission model is represented in [their documentation](https://docs.github.com/en/organizations/managing-access-to-your-organizations-repositories/repository-roles-for-an-organization#repository-roles-for-organizations).

In this tutorial, you will be focusing on a subset of these permissions.

Requirements:

- Users can be admins, maintainers, writers, triagers or readers of repositories (each level inherits all access of the level lower than it. e.g. admins inherit maintainer access and so forth)
- Teams can have members
- Organizations can have members
- Organizations can own repositories
- Users can have repository admin access on organizations, and thus have admin access to all repositories owned by that organization

##### Defined scenarios

There will be the following users:

- Anne
- Beth
- Charles, a member of the contoso/engineering team
- Diane, a member of the contoso/protocols team
- Erik, a member of the contoso org

And these requirements:

- members of the contoso/protocols team are members of the contoso/engineering team
- members of the contoso org are repo_admins on the org
- repo admins on the org are admins on all the repos the org owns

There will be a:

- contoso/tooling repository, owned by the contoso org and of which Beth is a writer and Anne is a reader and members of the contoso/engineering team are admins

#### Modeling GitHub's permissions

##### 01. Permissions For Individuals In An Org

GitHub has [5 different permission levels for repositories](https://docs.github.com/en/free-pro-team@latest/github/setting-up-and-managing-organizations-and-teams/repository-permission-levels-for-an-organization):

![Image showing github permission levels](https://github.com/openfga/openfga.dev/blob/main/./assets/github-permission-level.svg)

At the end of this section we want to end up with the following permissions represented:

![Image showing permissions](https://github.com/openfga/openfga.dev/blob/main/./assets/github-01.svg)

To represent permissions in <ProductName format={ProductNameFormat.LongForm}/> we use <ProductConcept section="what-is-a-relation" linkName="relations" />. For repository permissions we need to create the following <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'repo',
        relations: {
          reader: {
            this: {},
          },
          triager: {
            this: {},
          },
          writer: {
            this: {},
          },
          maintainer: {
            this: {},
          },
          admin: {
            this: {},
          },
        },
        metadata: {
          relations: {
            reader: { directly_related_user_types: [{ type: 'user' }] },
            triager: { directly_related_user_types: [{ type: 'user' }] },
            writer: { directly_related_user_types: [{ type: 'user' }] },
            maintainer: { directly_related_user_types: [{ type: 'user' }] },
            admin: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

The <ProductName format={ProductNameFormat.ShortForm}/> service determines if a <ProductConcept section="what-is-a-user" linkName="user" /> has access to an <ProductConcept section="what-is-an-object" linkName="object" /> by <ProductConcept section="what-is-a-check-request" linkName="checking" /> if the user has a relation to that object. Let us examine one of those relations in detail:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'repo', // objects of type "repo"
        relations: {
          // have users related to them as
          reader: {
            // "reader", if those users belong to
            this: {}, // the userset of all users related to the repo as "reader"
          },
        },
        metadata: {
          relations: {
            reader: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

:::info

Objects of type "repo" have users related to them as "reader" if those users belong to the userset of all users related to the repo as "reader"

:::

If we want to say `anne` is a reader of repository **repo:contoso/tooling** we create this <ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuple" />:

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:anne',
      relation: 'reader',
      object: 'repo:contoso/tooling',
    },
  ]}
/>

We can now <ProductConcept section="what-is-a-check-request" linkName="ask" /> <ProductName format={ProductNameFormat.ShortForm}/> "is `anne` a reader of repository **repo:contoso/tooling**?"

<CheckRequestViewer user={'user:anne'} relation={'reader'} object={'repo:contoso/tooling'} allowed={true} />

We could also say that `beth` is a writer of the same repository:

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:beth',
      relation: 'writer',
      object: 'repo:contoso/tooling',
    },
  ]}
/>

And ask some questions to <ProductName format={ProductNameFormat.ShortForm}/>:

<CheckRequestViewer user={'user:beth'} relation={'writer'} object={'repo:contoso/tooling'} allowed={true} />
<CheckRequestViewer user={'user:beth'} relation={'reader'} object={'repo:contoso/tooling'} allowed={false} />

The first reply makes sense but the second one does not. Intuitively, if `beth` was writer, she was also be a reader. In fact, GitHub explains this in [their documentation](https://docs.github.com/en/free-pro-team@latest/github/setting-up-and-managing-organizations-and-teams/repository-permission-levels-for-an-organization#repository-access-for-each-permission-level)
![Showing various GitHub repo access level](https://github.com/openfga/openfga.dev/blob/main/./assets/github-repo-access-level.svg)

To make <ProductName format={ProductNameFormat.ShortForm}/> aware of this "concentric" permission model we need to update our definitions:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'repo',
        relations: {
          reader: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'triager',
                  },
                },
              ],
            },
          },
          triager: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'writer',
                  },
                },
              ],
            },
          },
          writer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'maintainer',
                  },
                },
              ],
            },
          },
          maintainer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'admin',
                  },
                },
              ],
            },
          },
          admin: {
            this: {},
          },
        },
        metadata: {
          relations: {
            reader: { directly_related_user_types: [{ type: 'user' }] },
            triager: { directly_related_user_types: [{ type: 'user' }] },
            writer: { directly_related_user_types: [{ type: 'user' }] },
            maintainer: { directly_related_user_types: [{ type: 'user' }] },
            admin: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

Let us examine one of those relations in detail:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type: 'repo', // objects of type "repo"
    relations: {
      // have users related to them as
      reader: {
        // "reader": if they belong to
        union: {
          // any of (the union of) these user sets
          child: [
            {
              this: {}, // the userset of all users related to the repo as "reader"
            },
            {
              computedUserset: {
                // the userset of all users related to the repo as "triager"
                relation: 'triager',
              },
            },
          ],
        },
      },
    },
    metadata: {
      relations: {
        reader: { directly_related_user_types: [{ type: 'user' }] },
      },
    },
  }}
  skipVersion={true}
/>

:::info

The users with a reader relationship to a certain object of type "repo" are any of:

- the "readers": the set of users who are <ProductConcept section="what-are-direct-and-implied-relationships" linkName="directly related" /> to the repo as a "reader"
- the "triagers": the set of users who are related to the object as "triager"

:::

With this simple update our model now supports nested definitions and now:

<CheckRequestViewer user={'user:beth'} relation={'writer'} object={'repo:contoso/tooling'} allowed={true} />
<CheckRequestViewer user={'user:beth'} relation={'reader'} object={'repo:contoso/tooling'} allowed={true} />

##### 02. Permissions for teams in an org

GitHub also supports [creating teams in an organization](https://docs.github.com/en/free-pro-team@latest/github/setting-up-and-managing-organizations-and-teams/creating-a-team), [adding members to a team](https://docs.github.com/en/free-pro-team@latest/github/setting-up-and-managing-organizations-and-teams/adding-organization-members-to-a-team) and [granting teams permissions, rather than individuals](https://docs.github.com/en/free-pro-team@latest/github/setting-up-and-managing-organizations-and-teams/managing-team-access-to-an-organization-repository).

At the end of this section we want to end up with the following permissions represented:

![Image showing permissions](https://github.com/openfga/openfga.dev/blob/main/./assets/github-02.svg)

To add support for teams and memberships all we need to do is add this object to the <ProductName format={ProductNameFormat.ShortForm}/> <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'team', // objects of type "team"
        relations: {
          // have users related to them as
          member: {
            // "member", if those users belong to
            this: {}, // the userset of all users related to the repo as "member"
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }, { type: 'team', relation: 'member' }] },
          },
        },
      },
    ],
  }}
/>

In addition, the repo's relations should have team member as a directly related user types.

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'repo',
        relations: {
          reader: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'triager',
                  },
                },
              ],
            },
          },
          triager: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'writer',
                  },
                },
              ],
            },
          },
          writer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'maintainer',
                  },
                },
              ],
            },
          },
          maintainer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'admin',
                  },
                },
              ],
            },
          },
          admin: {
            this: {},
          },
        },
        metadata: {
          relations: {
            reader: { directly_related_user_types: [{ type: 'user' }, { type: 'team', relation: 'member' }] },
            triager: { directly_related_user_types: [{ type: 'user' }, { type: 'team', relation: 'member' }] },
            writer: { directly_related_user_types: [{ type: 'user' }, { type: 'team', relation: 'member' }] },
            maintainer: { directly_related_user_types: [{ type: 'user' }, { type: 'team', relation: 'member' }] },
            admin: { directly_related_user_types: [{ type: 'user' }, { type: 'team', relation: 'member' }] },
          },
        },
      },
    ],
  }}
/>

Let us now create a team, add a member to it and make it an admin of **repo:contoso/tooling** by adding the following <ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuples" />:

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'make charles a member of the contoso/engineering team',
      user: 'user:charles',
      relation: 'member',
      object: 'team:contoso/engineering',
    },
    {
      _description: 'make members of contoso/engineering team admins of contoso/tooling',
      user: 'team:contoso/engineering#member',
      relation: 'admin',
      object: 'repo:contoso/tooling',
    },
  ]}
/>

The last relationship tuple introduces a new **<ProductName format={ProductNameFormat.ShortForm}/>** concept. A **<ProductConcept section="what-is-a-user" linkName="userset" />**. When the value of a user is formatted like this **type:objectId#relation**, <ProductName format={ProductNameFormat.ShortForm}/> will automatically expand the userset into all its individual user identifiers:

<CheckRequestViewer user={'user:charles'} relation={'admin'} object={'repo:contoso/tooling'} allowed={true} />

##### 03. Permissions for child teams in an org

GitHub also supports team nesting, [known as "child teams"](https://docs.github.com/en/free-pro-team@latest/github/setting-up-and-managing-organizations-and-teams/requesting-to-add-a-child-team). **Child teams inherit the access permissions of the parent team.**
Let's say we have a **protocols** team that is part of the **engineering**. The simplest way to achieve the aforementioned requirement is just adding this <ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuple" />:

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'team:contoso/protocols#member',
      relation: 'member',
      object: 'team:contoso/engineering',
    },
  ]}
/>

which says that members of protocols are members of engineering.

> **Note:** this is enough and valid for our current requirements, and for other read cases allows determining members of the direct team vs sub teams as the latter come from **team:contoso/protocols#member**. If the #member relation should not be followed for use cases a different approach could be taken.

We can now add a member to the protocols team and check that they are admins of the tooling repository.

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'make diane a member of the contoso/protocols team',
      user: 'user:diane',
      relation: 'member',
      object: 'team:contoso/protocols',
    },
  ]}
/>

<CheckRequestViewer user={'user:diane'} relation={'admin'} object={'repo:contoso/tooling'} allowed={true} />

At the end of this section ended with the following permissions represented:

![Image showing permissions](https://github.com/openfga/openfga.dev/blob/main/./assets/github-03.svg)

##### 04. Base permissions for org members

In GitHub, ["you can set base permissions that apply to all members of an organization when accessing any of the organization's repositories"](https://docs.github.com/en/free-pro-team@latest/github/setting-up-and-managing-organizations-and-teams/setting-base-permissions-for-an-organization). For our purposes this means that if:

- User `erik` is a member of an organization `contoso`
- _and_ `contoso` has a repository `tooling`
- _and_ `contoso` has configured base permission to be "write"

then `erik` has write permissions to tooling.

Let us model that!

At the end of this section we want to end up with the following permissions represented:

![](https://raw.githubusercontent.com/openfga/openfga.dev/main/./assets/github-04.svg)

We need to introduce the notion of organization as a type, user organization membership and repository ownership as a relation. - It is worth calling that before this addition we were able to represent almost the entire GitHub repo permissions without adding the notion of organization to <ProductName format={ProductNameFormat.ShortForm}/>. Identifiers for users, repositories and teams were all that was necessary.
Let us add support for organizations and membership. Hopefully this feels familiar by now:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'organization', // objects of type "organization"
        relations: {
          // have users related to them as
          member: {
            // "member", if those users belong to
            this: {}, // the userset of all users related to the repo as "member"
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

And support for repositories having owners:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'repo',
        relations: {
          reader: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'triager',
                  },
                },
              ],
            },
          },
          triager: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'writer',
                  },
                },
              ],
            },
          },
          writer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'maintainer',
                  },
                },
              ],
            },
          },
          maintainer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'admin',
                  },
                },
              ],
            },
          },
          admin: {
            this: {},
          },
          owner: {
            // An organization can "own" a repository
            this: {},
          },
        },
        metadata: {
          relations: {
            reader: {
              directly_related_user_types: [{ type: 'user' }, { type: 'team', relation: 'member' }],
            },
            triager: {
              directly_related_user_types: [{ type: 'user' }, { type: 'team', relation: 'member' }],
            },
            writer: {
              directly_related_user_types: [{ type: 'user' }, { type: 'team', relation: 'member' }],
            },
            maintainer: {
              directly_related_user_types: [{ type: 'user' }, { type: 'team', relation: 'member' }],
            },
            admin: {
              directly_related_user_types: [{ type: 'user' }, { type: 'team', relation: 'member' }],
            },
            owner: {
              directly_related_user_types: [{ type: 'organization' }],
            },
          },
        },
      },
    ],
  }}
/>

:::info

Note the added "owner" relation, indicating that organizations can own repositories.

:::

We can now make Erik a member of contoso and make contoso own **contoso/tooling**:

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:erik',
      relation: 'member',
      object: 'organization:contoso',
    },
    {
      user: 'organization:contoso',
      relation: 'owner',
      object: 'repo:contoso/tooling',
    },
  ]}
/>

What we still lack is the ability to create "default permissions" for the organization and have those be considered when determining if a user has a particular relation to a repository. Let's start with the simplest case **admin**. We want to say that a user is an admin of a repo if either:

- [done] they have a repo admin relation (directly or through team membership)
- [pending] their organization is configured with **repo_admin** as the base permission

We need a way to consider the organization members, not just direct relations to the repo when getting a check for:

<CheckRequestViewer user={'user:erik'} relation={'admin'} object={'repo:contoso/tooling'} />

More details on this technique can be found in the section [Modeling Parent-Child Objects](https://github.com/openfga/openfga.dev/blob/main/../parent-child.mdx).

We express it like this:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'repo',
        relations: {
          admin: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  tupleToUserset: {
                    // read all tuples related to tooling as owner
                    // which returns [{ "user": "organization:contoso", "relation": "owner", "object": "repo:contoso/tooling" }]
                    tupleset: {
                      relation: 'owner',
                    },
                    // and for each tuple return all usersets that match the following, replacing $TUPLE_USERSET_OBJECT with organization:contoso
                    // this will return tuples of shape { object: "organization:contoso", "repo_admin", "user": ??? }
                    computedUserset: {
                      relation: 'repo_admin',
                    },
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            admin: {
              directly_related_user_types: [
                { type: 'user' },
                { type: 'team', relation: 'member' },
                { type: 'organization', relation: 'member' },
              ],
            },
          },
        },
      },
    ],
  }}
  skipVersion={true}
/>

:::info

The users with an admin relationship to a certain object of type "repo" are any of:

- the "admins": the set of users who are <ProductConcept section="what-are-direct-and-implied-relationships" linkName="directly related" /> to the repo as an "admin"
- the "repository admins of the org that owns the repo": from the objects who are related to the doc as owner, return the sets of users who are related to those objects as "repo_admin"

What the added section is doing is:

1. read all relationship tuples related to repo:contoso/tooling as owner which returns:

`[{ "object": "repo:contoso/tooling", "relation": "owner", "user": "organization:contoso" }]`

2. for each relationship tuple read, return all usersets that match the following, returning tuples of shape:

`{ "object": "organization:contoso", "relation": "repo_admin", "user": ??? }`

:::

What should the **users** in those relationship tuples with **???** be?

- Well:
  - If the base permission for org contoso is repo_admin then it should be **organization:contoso#member**.
  - If the base permission for org contoso is NOT repo_admin, then it should be empty (no relationship tuple).
- Whenever the value of this dropdown changes:
  ![Selecting new permission level from base permissions drop-down](https://github.com/openfga/openfga.dev/blob/main/./assets/github-org-base-permissions-drop-down.png)
  - Delete the previous relationship tuple and create a new one:
    <WriteRequestViewer
      relationshipTuples={[
        {
          user: 'organization:contoso#member',
          relation: 'repo_admin',
          object: 'organization:contoso',
        },
      ]}
    />

The updated authorization model looks like this:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'repo',
        relations: {
          admin: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  tupleToUserset: {
                    computedUserset: {
                      relation: 'repo_admin',
                    },
                    tupleset: {
                      relation: 'owner',
                    },
                  },
                },
              ],
            },
          },
          maintainer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'admin',
                  },
                },
              ],
            },
          },
          writer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'maintainer',
                  },
                },
                {
                  tupleToUserset: {
                    computedUserset: {
                      relation: 'writer',
                    },
                    tupleset: {
                      relation: 'owner',
                    },
                  },
                },
              ],
            },
          },
          triager: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'writer',
                  },
                },
              ],
            },
          },
          reader: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'triager',
                  },
                },
                {
                  tupleToUserset: {
                    computedUserset: {
                      relation: 'reader',
                    },
                    tupleset: {
                      relation: 'owner',
                    },
                  },
                },
              ],
            },
          },
          owner: {
            this: {},
          },
        },
        metadata: {
          relations: {
            reader: {
              directly_related_user_types: [
                { type: 'user' },
                { type: 'team', relation: 'member' },
                { type: 'organization', relation: 'member' },
              ],
            },
            triager: {
              directly_related_user_types: [
                { type: 'user' },
                { type: 'team', relation: 'member' },
                { type: 'organization', relation: 'member' },
              ],
            },
            writer: {
              directly_related_user_types: [
                { type: 'user' },
                { type: 'team', relation: 'member' },
                { type: 'organization', relation: 'member' },
              ],
            },
            maintainer: {
              directly_related_user_types: [
                { type: 'user' },
                { type: 'team', relation: 'member' },
                { type: 'organization', relation: 'member' },
              ],
            },
            admin: {
              directly_related_user_types: [
                { type: 'user' },
                { type: 'team', relation: 'member' },
                { type: 'organization', relation: 'member' },
              ],
            },
            owner: {
              directly_related_user_types: [{ type: 'organization' }],
            },
          },
        },
      },
      {
        type: 'organization',
        relations: {
          owner: {
            this: {},
          },
          repo_admin: {
            this: {},
          },
        },
        metadata: {
          relations: {
            owner: {
              directly_related_user_types: [{ type: 'organization' }],
            },
            repo_admin: {
              directly_related_user_types: [
                { type: 'user' },
                { type: 'team', relation: 'member' },
                { type: 'organization', relation: 'member' },
              ],
            },
          },
        },
      },
    ],
  }}
/>

#### Summary

GitHub has a number of other permissions. You have [organization billing managers, users that can manage specific apps, etc](https://docs.github.com/en/free-pro-team@latest/github/setting-up-and-managing-organizations-and-teams/permission-levels-for-an-organization). We might explore those in the future, but hopefully this blog post has shown you how you could represent those cases using <ProductName format={ProductNameFormat.LongForm}/>.

<Playground title="GitHub" preset="github" example="GitHub" store="github" />


<!-- End of openfga/openfga.dev/docs/content/modeling/advanced/github.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/advanced/iot.mdx -->

---
title: IoT
description: Modeling fine-grained authorization for an IoT security camera system
sidebar_position: 3
slug: /modeling/advanced/iot
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  IntroductionSection,
  Playground,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  WriteRequestViewer,
} from '@components/Docs';

### Modeling Authorization for an IoT Security System with <ProductName format={ProductNameFormat.ShortForm}/>

<DocumentationNotice />

This tutorial explains how to model permissions for an IoT system using <ProductName format={ProductNameFormat.ShortForm}/>.

<CardBox title="What you will learn">

- How to model a permission system using <ProductName format={ProductNameFormat.ProductLink}/>
- How to see <ProductName format={ProductNameFormat.ShortForm}/> Authorization in action by modeling an IoT Security Camera System

</CardBox>

<Playground title="IoT" preset="iot" example="IoT" store="iot" />

#### Before you start

In order to understand this guide correctly you must be familiar with some <ProductName format={ProductNameFormat.LongForm}/> concepts and know how to develop the things that we will list below.

<details>
<summary>

##### <ProductName format={ProductNameFormat.ShortForm}/> concepts

It would be helpful to have an understanding of some concepts of <ProductName format={ProductNameFormat.ShortForm}/> before you start.

</summary>

###### Direct access

You need to know how to create an authorization model and create a relationship tuple to grant a user access to an object. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../direct-access.mdx)

###### Modeling concentric relationships

You need to know how to update the authorization model to allow having nested relations such as all writers are readers. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../building-blocks/concentric-relationships.mdx)
Used here to indicate that both IT Admins and Security Guards can view live video.

###### Direct relationships

You need to know how to disallow granting direct relation to an object and requiring the user to have a relation with another object that would imply a relation with the first one. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../building-blocks/direct-relationships.mdx)
Used here to indicate that "Rename Device" is a permission that cannot be assigned directly, but can only be granted through the "IT Admin" role.

###### User groups

You need to know how to add users to groups and create relationships between groups of users and an object. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../building-blocks/direct-relationships.mdx)

Used here to indicate that security guards on a certain group are security guards on a device in that group.

###### Concepts & configuration language

- Some <ProductConcept />
- [Configuration Language](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx)

</details>

#### What You Will be modeling

In this tutorial, you will build an authorization model for a sample IoT Security Camera System (detailed below) using <ProductName format={ProductNameFormat.LongForm}/>. You will use some scenarios to validate the model.

The goal by the end of this post is to ask <ProductName format={ProductNameFormat.ShortForm}/>: Does person X have permission to perform action Y on device Z? In response, you want to either get a confirmation that person X can indeed do that, or a rejection that they cannot.

##### Requirements

These are the requirements:

- **Security guards** have access to **view live and recorded video** from **Devices**.
- **IT Admins** can **view live and recorded videos**, as well as **rename** **Devices**.
- To make access management easier, **Devices** can be grouped into **Device Groups**. **Security guards** with access to the **Device Group** are **Security Guards** with access to each **Device** in the group. Similarly for **IT Admins**.

##### Defined Scenarios

Use the following scenarios to be able to validate whether the model of the requirements is correct.

There will be the following users:

- Anne
- Beth
- Charles
- Dianne

These users have the following roles and permissions:

- Anne is a Security Guard with access to only Device 1
- Beth is an IT Admin with access to only Device 1
- Charles is a Security Guard with access to Device 1 and everything in Device Group 1 (which is Device 2 and Device 3)
- Dianne is an IT Admin with access to Device 1 and everything in Device Group 1

![Image showing requirements](https://github.com/openfga/openfga.dev/blob/main/./assets/iot-01.svg)

:::caution

In production, it is highly recommended to use unique, immutable identifiers. Names are used in this article to make it easier to read and follow.

:::

#### Modeling device authorization

The <ProductName format={ProductNameFormat.LongForm}/> service is based on [Zanzibar](https://zanzibar.academy), a Relationship Based Access Control system. This means it relies on <ProductConcept section="what-is-an-object" linkName="object" /> and <ProductConcept section="what-is-a-user" linkName="user" /> <ProductConcept section="what-is-a-relation" linkName="relations" /> to perform authorization <ProductConcept section="what-is-a-check-request" linkName="checks" />.

Starting with devices, you will learn how to express the requirements in terms of relations you can feed into <ProductName format={ProductNameFormat.LongForm}/>.

##### 01. Writing the initial model for a device

The requirements stated:

- **Security guards** have access to **view live and recorded video** from **Devices**.
- **IT Admins** can **view live and recorded videos**, as well as **rename** **Devices**.

The goal is to ask <ProductName format={ProductNameFormat.ShortForm}/> whether person X has permission to perform action Y on device Z. To start, you will set aside the Security Guard and IT Admin designations and focus on the actions a user can take.

The actions users can take on a device are: _view live videos_, _view recorded videos_, and _rename devices_. Mapping them to relations, they become: _live_video_viewer_, _recorded_video_viewer_, _device_renamer_.

In <ProductName format={ProductNameFormat.ShortForm}/>, the <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" /> for the device would be:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'device', // objects of type "device"
        relations: {
          // have users related to them as...
          live_video_viewer: {
            // "live_video_viewer": if they belong to the userset of all users related to the device as "live_video_viewer"
            this: {},
          },
          recorded_video_viewer: {
            // "recorded_video_viewer": if they belong to the userset of all users related to the device as "recorded_video_viewer"
            this: {},
          },
          device_renamer: {
            this: {}, // "device_renamer": if they belong to the userset of all users related to the device as "device_renamer"
          },
        },
        metadata: {
          relations: {
            live_video_viewer: { directly_related_user_types: [{ type: 'user' }] },
            recorded_video_viewer: { directly_related_user_types: [{ type: 'user' }] },
            device_renamer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

##### 02. Inserting some relationship tuples

The requirements are:

- **Anne** is a **Security Guard** with access to only **Device 1**
- **Beth** is an **IT Admin** with access to only **Device 1**
- **Security Guards** can **view live and recorded video**
- **IT Admins** can **view live and recorded video** and **rename** devices

Before we tackle the problem of users access to device based on their role, we will try to grant user access based on their view relationship directly.

We will first focus on Anne and Beth's relationship with Device 1.

To add Anne as live_video_viewer of device:1:

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:anne',
      relation: 'live_video_viewer',
      object: 'device:1',
    },
  ]}
/>

To add Anne as recorded_video_viewer of device:1

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:anne',
      relation: 'recorded_video_viewer',
      object: 'device:1',
    },
  ]}
/>

Likewise, we will add Beth's relationship with device:1.

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:beth',
      relation: 'live_video_viewer',
      object: 'device:1',
    },
    {
      user: 'user:beth',
      relation: 'recorded_video_viewer',
      object: 'device:1',
    },
    {
      user: 'user:beth',
      relation: 'device_renamer',
      object: 'device:1',
    },
  ]}
/>

###### Verification

Now that you have some relationship tuples added, you can start using it to <ProductConcept section="what-is-a-check-request" linkName="ask" /> some questions, e.g., whether a person has access to rename a device.

First, you will find out if `anne` has permission to `view the live video` on `device:1`, then you will see if `anne` can `rename` `device:1`.

Anne has `live_video_viewer` relationship with device:1.

<CheckRequestViewer user={'user:anne'} relation={'live_video_viewer'} object={'device:1'} allowed={true} />

On the other hand, Anne does not have `device_renamer` relationship with device:1.

<CheckRequestViewer user={'user:anne'} relation={'device_renamer'} object={'device:1'} allowed={false} />

Now, check the other relationships fore Anne and Beth.

| User   | Object     | Relation                | Query                                                   | Relation? |
| ------ | ---------- | ----------------------- | ------------------------------------------------------- | --------- |
| `anne` | `device:1` | `live_video_viewer`     | `is anne related to device:1 as live_video_viewer?`     | Yes       |
| `beth` | `device:1` | `live_video_viewer`     | `is beth related to device:1 as live_video_viewer?`     | Yes       |
| `anne` | `device:1` | `recorded_video_viewer` | `is anne related to device:1 as recorded_video_viewer?` | Yes       |
| `beth` | `device:1` | `recorded_video_viewer` | `is beth related to device:1 as recorded_video_viewer?` | Yes       |
| `anne` | `device:1` | `device_renamer`        | `is anne related to device:1 as device_renamer?`        | No        |
| `beth` | `device:1` | `device_renamer`        | `is beth related to device:1 as device_renamer?`        | Yes       |

##### 03. Updating our authorization model to facilitate future changes

Notice how you had to add the Anne and Beth as <ProductConcept section="what-are-direct-and-implied-relationships" linkName="direct relations" /> to all the actions they can take on Device 1 instead of just stating that they are related as Security Guard or IT Admin, and having the other permissions implied? In practice this might have some disadvantages: if your authorization model changes, (e.g so that Security Guards can no longer view previously recorded videos), you would need to change relationship tuples in the system instead of just changing the configuration.

We can address this by using [**concentric relation models**](https://github.com/openfga/openfga.dev/blob/main/../building-blocks/concentric-relationships.mdx). It allows you to express that sets of users who have a relation X to the object also have relation Y. For example, anyone that is related to the device as a `security_guard` is also related as a `live_video_viewer` and `recorded_video_viewer`, and anyone who is related to the device as an `it_admin` is also related as a `live_video_viewer`, a `recorded_video_viewer`, and a `device_renamer`.

At the end you want to make sure that <ProductConcept section="what-is-a-check-request" linkName="checking" /> if Anne, Beth, Charles, or Dianne have permission to view the live video or rename the device, will get you the correct answers back.

The resulting authorization model is:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'device',
        relations: {
          it_admin: {
            this: {},
          },
          security_guard: {
            this: {},
          },
          live_video_viewer: {
            // objects of type "device" have users related to them as "live_video_viewer", if they belong to:
            union: {
              // any of (the union of)
              child: [
                {
                  this: {}, // the userset of all users related to the github-repo as "repo_reader"
                },
                {
                  computedUserset: {
                    //  The userset of all users who are related to the device as it_admin
                    relation: 'it_admin',
                  },
                },
                {
                  computedUserset: {
                    // The userset of all users who are related to the device as it_admin
                    relation: 'security_guard',
                  },
                },
              ],
            },
          },
          recorded_video_viewer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'it_admin',
                  },
                },
                {
                  computedUserset: {
                    relation: 'security_guard',
                  },
                },
              ],
            },
          },
          device_renamer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'it_admin',
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            it_admin: { directly_related_user_types: [{ type: 'user' }] },
            security_guard: { directly_related_user_types: [{ type: 'user' }] },
            live_video_viewer: { directly_related_user_types: [{ type: 'user' }] },
            recorded_video_viewer: { directly_related_user_types: [{ type: 'user' }] },
            device_renamer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

The requirements are:

- **Anne** and **Charles** are **Security Guards** with access **Device 1**
- **Beth** and **Dianne** are **IT Admins** with access **Device 1**
- **Security Guards** can **view live and recorded video**
- **IT Admins** can **view live and recorded video** and **rename** devices

Instead of adding different relationship tuples with direct relations to the actions they can take, as you did in the previous section, you will only add the relation to their role: `it_admin` or `security_guard`.

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:anne',
      relation: 'security_guard',
      object: 'device:1',
    },
    {
      user: 'user:beth',
      relation: 'it_admin',
      object: 'device:1',
    },
    {
      user: 'user:charles',
      relation: 'security_guard',
      object: 'device:1',
    },
    {
      user: 'user:dianne',
      relation: 'it_admin',
      object: 'device:1',
    },
  ]}
/>

###### Verification

We can now verify whether charles is related to device:1 as live_video_viewer.

<CheckRequestViewer user={'user:charles'} relation={'live_video_viewer'} object={'device:1'} allowed={true} />

Check the other relationships for anne, beth, charles and dianne.

| User      | Object     | Relation                | Query                                                      | Relation? |
| --------- | ---------- | ----------------------- | ---------------------------------------------------------- | --------- |
| `anne`    | `device:1` | `live_video_viewer`     | `is anne related to device:1 as live_video_viewer?`        | Yes       |
| `beth`    | `device:1` | `live_video_viewer`     | `is beth related to device:1 as live_video_viewer?`        | Yes       |
| `anne`    | `device:1` | `recorded_video_viewer` | `is anne related to device:1 as recorded_video_viewer?`    | Yes       |
| `beth`    | `device:1` | `recorded_video_viewer` | `is beth related to device:1 as recorded_video_viewer?`    | Yes       |
| `anne`    | `device:1` | `device_renamer`        | `is anne related to device:1 as device_renamer?`           | No        |
| `beth`    | `device:1` | `device_renamer`        | `is beth related to device:1 as device_renamer?`           | Yes       |
| `charles` | `device:1` | `live_video_viewer`     | `is charles related to device:1 as live_video_viewer?`     | Yes       |
| `dianne`  | `device:1` | `live_video_viewer`     | `is dianne related to device:1 as live_video_viewer?`      | Yes       |
| `charles` | `device:1` | `recorded_video_viewer` | `is charles related to device:1 as recorded_video_viewer?` | Yes       |
| `dianne`  | `device:1` | `recorded_video_viewer` | `is dianne related to device:1 as recorded_video_viewer?`  | Yes       |
| `charles` | `device:1` | `device_renamer`        | `is charles related to device:1 as device_renamer?`        | No        |
| `dianne`  | `device:1` | `device_renamer`        | `is dianne related to device:1 as device_renamer?`         | Yes       |

##### 04. Modeling device groups

Now that you are done with devices. Let us tackle device groups.

The requirements regarding device groups were:

- **Devices** can be grouped into **Device Groups**
- **Security guards** with access to the **Device Group** are **Security Guards** with access to the **Devices** within the **Device Group**. Similarly for **IT Admins**

The <ProductConcept section="what-is-a-type-definition" linkName="type definition" /> for the device group:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type: 'device_group',
    relations: {
      it_admin: {
        this: {},
      },
      security_guard: {
        this: {},
      },
    },
    metadata: {
      relations: {
        it_admin: { directly_related_user_types: [{ type: 'user' }] },
        security_guard: { directly_related_user_types: [{ type: 'user' }] },
      },
    },
  }} skipVersion={true}
/>

With this change, the full <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" /> becomes:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'device',
        relations: {
          it_admin: {
            this: {},
          },
          security_guard: {
            this: {},
          },
          live_video_viewer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'it_admin',
                  },
                },
                {
                  computedUserset: {
                    relation: 'security_guard',
                  },
                },
              ],
            },
          },
          recorded_video_viewer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'it_admin',
                  },
                },
                {
                  computedUserset: {
                    relation: 'security_guard',
                  },
                },
              ],
            },
          },
          device_renamer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'it_admin',
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            it_admin: {
              directly_related_user_types: [{ type: 'user' }, { type: 'device_group', relation: 'it_admin' }],
            },
            security_guard: {
              directly_related_user_types: [{ type: 'user' }, { type: 'device_group', relation: 'security_guard' }],
            },
            live_video_viewer: { directly_related_user_types: [{ type: 'user' }] },
            recorded_video_viewer: { directly_related_user_types: [{ type: 'user' }] },
            device_renamer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'device_group',
        relations: {
          it_admin: {
            this: {},
          },
          security_guard: {
            this: {},
          },
        },
        metadata: {
          relations: {
            it_admin: { directly_related_user_types: [{ type: 'user' }] },
            security_guard: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

###### Updating relationship tuples on roles

Remember that **Charles** is a **Security Guard**, and **Dianne** an **IT Admin** on **Group 1**, enter the <ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuples" /> below to reflect that.

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:charles',
      relation: 'security_guard',
      object: 'device_group:group1',
    },
    {
      user: 'user:dianne',
      relation: 'it_admin',
      object: 'device_group:group1',
    },
  ]}
/>

You still need to give all the security guards of group1 a `security_guard` relation to devices 2 and 3, and similarly for IT Admins. Add the following relationship tuples to do that.

<WriteRequestViewer
  relationshipTuples={[
    {
      // the userset of all users related to “device_group:group1” as “security_guard” are related to device:2 as “security_guard”
      user: 'device_group:group1#security_guard',
      relation: 'security_guard',
      object: 'device:2',
    },
    {
      user: 'device_group:group1#security_guard',
      relation: 'security_guard',
      object: 'device:3',
    },
    {
      // the userset of all users related to “device_group:group1” as “it_admin” are related to device:2 as “it_admin”
      user: 'device_group:group1#it_admin',
      relation: 'it_admin',
      object: 'device:2',
    },
    {
      user: 'device_group:group1#it_admin',
      relation: 'it_admin',
      object: 'device:3',
    },
  ]}
/>

###### Verification

Now that you have finalized the model and added the relationship tuples, you can start asking some queries. Try asking the same queries you did earlier but on device 2 instead of device 1.

We can ask `is dianne related to device:2 as live_video_viewer?`

<CheckRequestViewer user={'dianne'} relation={'live_video_viewer'} object={'device:2'} allowed={true} />

Type any of the following queries in the **TUPLE QUERIES** section and press **ENTER** on your keyboard to see the results.

| User      | Object     | Relation                | Query                                                      | Relation? |
| --------- | ---------- | ----------------------- | ---------------------------------------------------------- | --------- |
| `anne`    | `device:2` | `live_video_viewer`     | `is anne related to device:2 as live_video_viewer?`        | No        |
| `beth`    | `device:2` | `live_video_viewer`     | `is beth related to device:2 as live_video_viewer?`        | No        |
| `anne`    | `device:2` | `recorded_video_viewer` | `is anne related to device:2 as recorded_video_viewer?`    | No        |
| `beth`    | `device:2` | `recorded_video_viewer` | `is beth related to device:2 as recorded_video_viewer?`    | No        |
| `anne`    | `device:2` | `device_renamer`        | `is anne related to device:2 as device_renamer?`           | No        |
| `beth`    | `device:2` | `device_renamer`        | `is beth related to device:2 as device_renamer?`           | No        |
| `charles` | `device:2` | `live_video_viewer`     | `is charles related to device:2 as live_video_viewer?`     | Yes       |
| `dianne`  | `device:2` | `live_video_viewer`     | `is dianne related to device:2 as live_video_viewer?`      | Yes       |
| `charles` | `device:2` | `recorded_video_viewer` | `is charles related to device:2 as recorded_video_viewer?` | Yes       |
| `dianne`  | `device:2` | `recorded_video_viewer` | `is dianne related to device:2 as recorded_video_viewer?`  | Yes       |
| `charles` | `device:2` | `device_renamer`        | `is charles related to device:2 as device_renamer?`        | No        |
| `dianne`  | `device:2` | `device_renamer`        | `is dianne related to device:2 as device_renamer?`         | Yes       |

##### 05. Disallow direct relationships To users

Notice that despite following **[Step 03](https://github.com/openfga/openfga.dev/blob/main/./iot.mdx#03-updating-our-authorization-model-to-facilitate-future-changes)**, anne and beth still have direct relations to all the actions they can take on device:1.

###### Updating the authorization model

`anne` is a `live_video_viewer` by both her position as `security_guard` as well as her _<ProductConcept section="what-are-direct-and-implied-relationships" linkName="direct relationship" />_ assignment. This is undesirable. Imagine `anne` left her position of `security_guard` and she will still have `live_video_viewer` access to `device:1`.

To remedy this, remove `[user]` from `live_video_viewer`, `recorded_video_viewer` and `device_renamer`. This denies direct relations to `live_video_viewer`, `recorded_video_viewer` and `device_renamer` from having an effect. To do this:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'device',
        relations: {
          it_admin: {
            this: {},
          },
          security_guard: {
            this: {},
          },
          live_video_viewer: {
            union: {
              child: [
                {
                  computedUserset: {
                    relation: 'it_admin',
                  },
                },
                {
                  computedUserset: {
                    relation: 'security_guard',
                  },
                },
              ],
            },
          },
          recorded_video_viewer: {
            union: {
              child: [
                {
                  computedUserset: {
                    relation: 'it_admin',
                  },
                },
                {
                  computedUserset: {
                    relation: 'security_guard',
                  },
                },
              ],
            },
          },
          device_renamer: {
            union: {
              child: [
                {
                  computedUserset: {
                    relation: 'it_admin',
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            it_admin: {
              directly_related_user_types: [{ type: 'user' }, { type: 'device_group', relation: 'it_admin' }],
            },
            security_guard: {
              directly_related_user_types: [{ type: 'user' }, { type: 'device_group', relation: 'security_guard' }],
            },
          },
        },
      },
      {
        type: 'device_group',
        relations: {
          it_admin: {
            this: {},
          },
          security_guard: {
            this: {},
          },
        },
        metadata: {
          relations: {
            it_admin: { directly_related_user_types: [{ type: 'user' }] },
            security_guard: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

:::info

Notice that any reference to the [**direct relationship type restrictions**](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx#direct-relationship-type-restrictions) has been removed. That indicates that a user cannot have a <ProductConcept section="what-are-direct-and-implied-relationships" linkName="direct relationship" /> with an object in this type.

With this change, `anne` can no longer have a `live_video_viewer` permission for `device:1` except through having a `security_guard` or `it_admin` role first, and when she loses access to that role, she will automatically lose access to the `live_video_viewer` permission.

:::

###### Verification

Now that direct relationship is denied, we should see that `anne` has `live_video_viewer` relation to `device:1` solely based on her position as `security_guard` to `device:1`. Let's find out.

To test this, we can add a new user `emily`. Emily is **not** a `security_guard` nor an `it_admin`. However, we attempt to access via direct relations by adding the following <ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuples" />:

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:emily',
      relation: 'live_video_viewer',
      object: 'device:1',
    },
    {
      user: 'user:emily',
      relation: 'recorded_video_viewer',
      object: 'device:1',
    },
    {
      user: 'user:emily',
      relation: 'device_renamer',
      object: 'device:1',
    },
  ]}
/>

Now try to query `is emily related to device:1 as live_video_viewer?`. The returned result should be `emily is not related to device:1 as live_video_viewer`. This confirms that direct relations have no effect on the `live_video_viewer` relations, and that is because the [**direct relationship type restriction**](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx#direct-relationship-type-restrictions) was removed from the relation configuration.

<CheckRequestViewer user={'user:emily'} relation={'live_video_viewer'} object={'device:1'} allowed={false} />

Query on the other relationships and you will see:

| User    | Object     | Relation                | Query                                                    | Relation? |
| ------- | ---------- | ----------------------- | -------------------------------------------------------- | --------- |
| `emily` | `device:1` | `recorded_video_viewer` | `is emily related to device:1 as recorded_video_viewer?` | No        |
| `emily` | `device:1` | `device_renamer`        | `is emily related to device:1 as device_renamer?`        | No        |

#### Summary

In this post, you were introduced to <IntroductionSection linkName="fine grain authentication" section="what-is-fine-grained-authorization"/> and <ProductName format={ProductNameFormat.LongForm}/>.

Upcoming posts will dive deeper into <ProductName format={ProductNameFormat.LongForm}/>, introducing concepts that will improve on the model you built today, and tackling more complex permission systems, with more relations and requirements that need to be met.

<Playground title="IoT" preset="iot" example="IoT" store="iot" />

##### Exercises for you

- Try adding a second group tied to devices 4 and 5. Add only Charles and Dianne to this group, then try to run queries that would validate your model.
- Management has decided that Security Guards can only access live videos, and instituted a new position called Security Officer who can view both live and recorded videos. Can you update the authorization model to reflect that?


<!-- End of openfga/openfga.dev/docs/content/modeling/advanced/iot.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/advanced/overview.mdx -->

---
id: overview
title: 'Advanced Use-Cases'
slug: /modeling/advanced
sidebar_position: 0
description: Advanced use cases and patterns for authorization modeling
---

import { CardGrid, DocumentationNotice, IntroCard, ProductName, ProductNameFormat } from '@components/Docs';

<DocumentationNotice />

This section will present advanced use cases and patterns for authorization modeling with <ProductName format={ProductNameFormat.LongForm}/>.

<IntroCard
  title="When to use"
  description="The content in this section is useful if you would like to follow an end-to-end tutorial on how to build an authorization model for a common use-case or pattern."
/>

#### Use-cases

<CardGrid
  middle={[
    {
      title: 'Google Drive',
      description: 'How to create an authorization model for your system starting from the requirements.',
      to: 'advanced/gdrive',
    },
    {
      title: 'GitHub',
      description: 'How to create an authorization model for your system starting from the requirements.',
      to: 'advanced/github',
    },
    {
      title: 'IoT',
      description: 'How to create an authorization model for your system starting from the requirements.',
      to: 'advanced/iot',
    },
    {
      title: 'Slack',
      description: 'How to create an authorization model for your system starting from the requirements.',
      to: 'advanced/slack',
    },
  ]}
/>

#### Patterns

<CardGrid
  middle={[
    {
      title: 'Entitlements',
      description: 'How to create an authorization model for your system starting from the requirements.',
      to: 'advanced/entitlements',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/advanced/overview.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/advanced/slack.mdx -->

---
title: Slack
description: Modeling authorization for Slack
sidebar_position: 4
slug: /modeling/advanced/slack
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  IntroductionSection,
  Playground,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  WriteRequestViewer,
} from '@components/Docs';

### Modeling Authorization for Slack with <ProductName format={ProductNameFormat.ShortForm}/>

<DocumentationNotice />

This tutorial explains how to model permissions for a communication platform like Slack using <ProductName format={ProductNameFormat.ShortForm}/>.

<CardBox title="What you will learn">

- How to indicate relationships between a group of **<ProductConcept section="what-is-a-user" linkName="users" />** and an **<ProductConcept section="what-is-an-object" linkName="object" />**.<br />
  Used here to indicate that all members of a slack workspace can write in a certain channel.<br />
  See [Modeling User Groups](https://github.com/openfga/openfga.dev/blob/main/../user-groups.mdx) for more.
- How to Model **concentric relationship** to have a certain <ProductConcept section="what-is-a-relation" linkName="relation" /> on an object imply another relation on the same object.<br />
  Used here to indicate that legacy admins have all the permissions of the more granular channels admin.<br />
  See [Modeling Concentric Relationships](https://github.com/openfga/openfga.dev/blob/main/../building-blocks/concentric-relationships.mdx) for more.
- How to use [**the union operator**](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx#the-union-operator) condition to indicate that a user might have a certain relation with an object if they match any of the criteria indicated.

</CardBox>

<Playground title="Slack" preset="slack" example="Slack" store="slack" />

#### Before you start

In order to understand this guide correctly you must be familiar with some <ProductName format={ProductNameFormat.LongForm}/> concepts and know how to develop the things that we will list below.

<details>
<summary>

##### <ProductName format={ProductNameFormat.ShortForm}/> concepts

It would be helpful to have an understanding of some concepts of <ProductName format={ProductNameFormat.ShortForm}/> before you start.

</summary>

###### Direct access

You need to know how to create an authorization model and create a relationship tuple to grant a user access to an object. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../direct-access.mdx)

###### Modeling concentric relationships

You need to know how to update the authorization model to allow having nested relations such as all writers are readers. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../building-blocks/concentric-relationships.mdx)

###### Concepts & configuration language

- Some <ProductConcept />
- [Configuration Language](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx)

</details>

#### What you will be modeling

Slack is a messaging app for businesses that connects people to the information they need. By bringing people together to work as one unified team, Slack transforms the way organizations communicate. (Source: [What is Slack?](https://slack.com/intl/en-ca/help/articles/115004071768-What-is-Slack-))

In this tutorial, you will build a subset of the Slack permission model (detailed below) in <ProductName format={ProductNameFormat.LongForm}/>, using some scenarios to validate the model.

> As reference, you can refer to Slack's publicly available docs:
>
> - [Role Management at Slack](https://slack.engineering/role-management-at-slack/)
> - [Types of Roles in Slack](https://slack.com/intl/en-ca/help/articles/360018112273-Types-of-roles-in-Slack)
> - [Permissions by Role in Slack](https://slack.com/intl/en-ca/help/articles/201314026-Permissions-by-role-in-Slack)
> - [Manage a Workspace on Enterprise Grid](https://slack.com/intl/en-ca/help/articles/115005225987-Manage-a-workspace-on-Enterprise-Grid)
> - [Manage channel posting permissions](https://slack.com/intl/en-ca/help/articles/360004635551-Manage-channel-posting-permissions-)

> Note: For brevity, this tutorial will not model all of Slack's permissions. Instead, it will focus on modeling the scenarios outlined below.

##### Requirements

This tutorial will focus on the following sections (this is a partial list of Slack's roles):

Workspace Roles:

- **Guest**: This type of user is limited in their ability to use Slack, and is only permitted to see one or multiple delegated channels.
- **Member**: This is the base type of user that does not have any particular administrative abilities, but has basic access to the organization's Slack workspaces. When an administrative change needs to be made, these users need the support of admins and owners to make the changes.
- **Legacy Admin**: This type of user is the basic administrator of any organization, and can make a wide variety of administrative changes across Slack, such as renaming channels, archiving channels, setting up preferences and policies, inviting new users, and installing applications. Users with this role perform the majority of administrative tasks across a team.

System Roles:

- **Channels Admin**: This type of user has the permission to archive channels, rename channels, create private channels, and convert public channels into private channels.

Channel Settings:

- **Visibility**:
  - **Public**: Visible to all members and open to join
  - **Private**: Visible to admins and invited members
- [**Posting Permissions**](https://slack.com/intl/en-ca/help/articles/360004635551-Manage-channel-posting-permissions-):
  - **Open**: Anyone can post
  - **Limited**: Only allowed members can post

##### Defined scenarios

Use the following scenarios to be able to validate whether the model of the requirements is correct.

There will be the following users:

- Amy
- Bob
- Catherine
- David
- Emily

These users will interact in the following scenarios:

- You will assume there is a Slack workspace called Sandcastle
- Amy is a legacy admin of the Sandcastle workspace
- Bob is a member of the Sandcastle workspace with a channels admin role (Read more about system roles at Slack [here](https://slack.engineering/role-management-at-slack/))
- Catherine and Emily are normal members of the Sandcastle workspace, they can view all public channels, as well as channels they have been invited to
- David is a guest user with only view and write access to #proj-marketing-campaign, one of the public channels in the Sandcastle workspace
- Bob and Emily are in a private channel #marketing-internal in the Sandcastle workspace which only they can view and post to
- All members of the Sandcastle workspace can view the general channel, but only Amy and Emily can post to it

![Image showing requirements](https://github.com/openfga/openfga.dev/blob/main/./assets/slack-01.svg)

:::caution

In production, it is highly recommended to use unique, immutable identifiers. Names are used in this article to make it easier to read and follow.

:::

#### Modeling workspaces & channels

The goal by the end of this post is to ask <ProductName format={ProductNameFormat.LongForm}/>: Does person X have permission to perform action Y on channel Z? In response, you want to either get a confirmation that person X can indeed do that, or a rejection that they cannot. E.g. does David have access to view #general?

The <ProductName format={ProductNameFormat.LongForm}/> is based on [Zanzibar](https://zanzibar.academy), a Relation Based Access Control system. This means it relies on <ProductConcept section="what-is-an-object" linkName="objects" /> and <ProductConcept section="what-is-a-user" linkName="user" /> <ProductConcept section="what-is-a-relation" linkName="relations" /> to perform authorization <ProductConcept section="what-is-a-check-request" linkName="checks" />.

Setting aside the permissions, you will start with the roles and learn how to express the requirements in terms of relations you can feed into <ProductName format={ProductNameFormat.ShortForm}/>.

The requirements stated:

- **Amy** is a **legacy admin** of the **Sandcastle workspace**
- **Bob** is a **channels admin** of the **Sandcastle workspace**
- **Catherine** and **Emily** are a normal **members** of the **Sandcastle workspace**
- **David** is a **guest** user

Here is how you would express than in <ProductName format={ProductNameFormat.ShortForm}/>'s <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />: You have a <ProductConcept section="what-is-a-type" linkName="type" /> called "workspace", and users can be related to it as a legacy_admin, channels_admin, member and guest

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
    {
      type: 'user',
    },
    {
      type: 'workspace', // objects of type workspace
      relations: {
        // have users related to them as...
        legacy_admin: {
          // Legacy Admins
          this: {},
        },
        channels_admin: {
          // Channels Admin
          this: {},
        },
        member: {
          // Member
          this: {},
        },
        guest: {
          // Guest
          this: {},
        },
      },
      metadata: {
        relations: {
          legacy_admin: { directly_related_user_types: [{ type: 'user' }] },
          channels_admin: { directly_related_user_types: [{ type: 'user' }] },
          member: { directly_related_user_types: [{ type: 'user' }] },
          guest: { directly_related_user_types: [{ type: 'user' }] },
        },
      },
    }]
  }}
/>

:::info

**Objects** of type `workspace` have users related to them as:

- Legacy Admin (`legacy_admin`)
- Channels Admin (`channels_admin`)
- Member (`member`)
- Guest (`guest`)

[Direct relationship type restrictions](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx#direct-relationship-type-restrictions) indicate that a user can have a <ProductConcept section="what-are-direct-and-implied-relationships" linkName="direct relationship" /> with an object of the type the relation specifies.

:::

##### 01. Individual permissions

To keep things simple and focus on <ProductName /> rather than Slack complexity, we will model only four roles (legacy_admin, channels_admin, member, guest).

At the end of this section we want to have the following permissions represented

| User      | Relation       | Object               |
| --------- | -------------- | -------------------- |
| amy       | legacy_admin   | workspace:sandcastle |
| bob       | channels_admin | workspace:sandcastle |
| catherine | member         | workspace:sandcastle |
| david     | guest          | workspace:sandcastle |
| emily     | member         | workspace:sandcastle |

To represent permissions in <ProductName /> we use <ProductConcept section="what-is-a-relation" linkName="relations" />. For workspace permissions we need to create the following <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'workspace',
        relations: {
          legacy_admin: {
            this: {},
          },
          channels_admin: {
            this: {},
          },
          member: {
            this: {},
          },
          guest: {
            this: {},
          },
        },
        metadata: {
          relations: {
            legacy_admin: { directly_related_user_types: [{ type: 'user' }] },
            channels_admin: { directly_related_user_types: [{ type: 'user' }] },
            member: { directly_related_user_types: [{ type: 'user' }] },
            guest: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

The <ProductName format={ProductNameFormat.LongForm}/> service determines if a <ProductConcept section="what-is-a-user" linkName="user" /> has access to an <ProductConcept section="what-is-an-object" linkName="object" /> by <ProductConcept section="what-is-a-check-request" linkName="checking" /> if the user has a relation to that object. Let us examine one of those relations in detail:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type: 'workspace', // objects of type workspace
    relations: {
      // have users related to them as...
      member: {
        // "member": if those users belong to:
        this: {}, // the userset of all users related to the document as "member"
      },
    },
    metadata: {
      relations: {
        member: { directly_related_user_types: [{ type: 'user' }] },
      },
    },
  }} skipVersion={true}
/>

:::info

The snippet above indicates that objects of type workspace have users related to them as "member" if those users belong to the userset of all users related to the workspace as "member".

This means that a user can be <ProductConcept section="what-are-direct-and-implied-relationships" linkName="directly related" /> as a member to an object of type "workspace"

:::

If we want to say `amy` is a `legacy_admin` of `workspace:sandcastle` we create this relationship tuple

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'Amy is a Legacy Admin in the Sandcastle workspace',
      user: 'user:amy',
      relation: 'legacy_admin',
      object: 'workspace:sandcastle',
    },
  ]}
/>

We can now ask <ProductName /> "is `amy` a legacy_admin of **workspace:sandcastle**?"

<CheckRequestViewer user={'user:amy'} relation={'legacy_admin'} object={'workspace:sandcastle'} allowed={true} />

We can also say that `catherine` is a `member` of `workspace:sandcastle`:

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'Catherine is a Member in the Sandcastle workspace',
      user: 'user:catherine',
      relation: 'member',
      object: 'workspace:sandcastle',
    },
  ]}
/>

And verify by <ProductConcept section="what-is-a-check-request" linkName="asking" /> <ProductName />

<CheckRequestViewer user={'user:catherine'} relation={'member'} object={'workspace:sandcastle'} allowed={true} />

Catherine, on the other hand, is not a legacy_admin of workspace:sandcastle.

<CheckRequestViewer user={'user:catherine'} relation={'legacy_admin'} object={'workspace:sandcastle'} allowed={false} />

Repeat this process for the other relationships

```json
[
  {
    // Bob is a Channels Admin in the Sandcastle workspace
    user: 'user:bob',
    relation: 'channels_admin',
    object: 'workspace:sandcastle',
  },
  {
    // David is a guest in the Sandcastle workspace
    user: 'user:david',
    relation: 'guest',
    object: 'workspace:sandcastle',
  },
  {
    // Emily is a Member in the Sandcastle workspace
    user: 'user:emily',
    relation: 'member',
    object: 'workspace:sandcastle',
  },
]
```

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'Bob is a Channels Admin in the Sandcastle workspace',
      user: 'user:bob',
      relation: 'channels_admin',
      object: 'workspace:sandcastle',
    },
    {
      _description: 'David is a guest in the Sandcastle workspace',
      user: 'user:david',
      relation: 'guest',
      object: 'workspace:sandcastle',
    },
    {
      _description: 'Emily is a Member in the Sandcastle workspace',
      user: 'user:emily',
      relation: 'member',
      object: 'workspace:sandcastle',
    },
  ]}
/>

###### Verification

To verify, we can issue <ProductConcept section="what-is-a-check-request" linkName="check request" /> to verify it is working as expected.

<CheckRequestViewer user={'user:amy'} relation={'legacy_admin'} object={'workspace:sandcastle'} allowed={true} />

Let's try to verify the followings:

| User    | Object                 | Relation       | Query                                                       | Relation? |
| ------- | ---------------------- | -------------- | ----------------------------------------------------------- | --------- |
| `amy`   | `workspace:sandcastle` | `legacy_admin` | `is amy related to workspace:sandcastle as legacy_admin?`   | Yes       |
| `david` | `workspace:sandcastle` | `legacy_admin` | `is david related to workspace:sandcastle as legacy_admin?` | No        |
| `amy`   | `workspace:sandcastle` | `guest`        | `is amy related to workspace:sandcastle as guest?`          | No        |
| `david` | `workspace:sandcastle` | `guest`        | `is david related to workspace:sandcastle as guest?`        | Yes       |
| `amy`   | `workspace:sandcastle` | `member`       | `is amy related to workspace:sandcastle as member?`         | No        |
| `david` | `workspace:sandcastle` | `member`       | `is david related to workspace:sandcastle as member?`       | No        |

##### 02. Updating The `workspace` Authorization Model With Implied Relations

Some of the queries that you ran earlier, while returning the correct response, do not match reality. One of which is:

<CheckRequestViewer user={'user:amy'} relation={'member'} object={'workspace:sandcastle'} allowed={false} />

As you saw before, running this query will return `amy is not a member of workspace:sandcastle`, which is correct based on the data you have given <ProductName format={ProductNameFormat.LongForm}/> so far. But in reality, Amy, who is a `legacy_admin` already has an <ProductConcept section="what-are-direct-and-implied-relationships" linkName="implied" /> `channels_admin` and `member` relations. In fact anyone (other than a guest) is a `member` of the workspace.

To change this behavior, we will update our system with a [**concentric relationship**](https://github.com/openfga/openfga.dev/blob/main/../building-blocks/concentric-relationships.mdx) model.

With the following updated <ProductConcept section="what-is-a-type-definition" linkName="authorization model" />, you are informing <ProductName format={ProductNameFormat.LongForm}/> that any user who is related to a workspace as `legacy_admin`, is also related as a `channels_admin` and a `member` .

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'workspace',
        relations: {
          legacy_admin: {
            this: {},
          },
          channels_admin: {
            // users related to `workspace` as `channels_admin` are
            union: {
              // the union (any of):
              child: [
                {
                  this: {}, // the set of users with a direct `channels_admin` relation
                },
                {
                  computedUserset: {
                    // the set of users related to the workspace as `legacy_admin`
                    relation: 'legacy_admin',
                  },
                },
              ],
            },
          },
          member: {
            // users related to `workspace` as `member` are
            union: {
              // the union (any of):
              child: [
                {
                  this: {}, // the set of users with a direct `member` relation
                },
                {
                  computedUserset: {
                    // the set of users related to the workspace as `channels_admin`
                    relation: 'channels_admin',
                  },
                },
                {
                  computedUserset: {
                    // the set of users related to the workspace as `legacy_admin`
                    relation: 'legacy_admin',
                  },
                },
              ],
            },
          },
          guest: {
            this: {},
          },
        },
        metadata: {
          relations: {
            legacy_admin: { directly_related_user_types: [{ type: 'user' }] },
            channels_admin: { directly_related_user_types: [{ type: 'user' }] },
            member: { directly_related_user_types: [{ type: 'user' }] },
            guest: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

We can then verify `amy` is a `member` of `workspace:sandcastle`.

<CheckRequestViewer user={'user:amy'} relation={'member'} object={'workspace:sandcastle'} allowed={true} />

We can check for other users and relationships.

| User    | Object                 | Relation       | Query                                                       | Relation? |
| ------- | ---------------------- | -------------- | ----------------------------------------------------------- | --------- |
| `amy`   | `workspace:sandcastle` | `legacy_admin` | `is amy related to workspace:sandcastle as legacy_admin?`   | Yes       |
| `david` | `workspace:sandcastle` | `legacy_admin` | `is david related to workspace:sandcastle as legacy_admin?` | No        |
| `amy`   | `workspace:sandcastle` | `guest`        | `is amy related to workspace:sandcastle as guest?`          | No        |
| `david` | `workspace:sandcastle` | `guest`        | `is david related to workspace:sandcastle as guest?`        | Yes       |
| `amy`   | `workspace:sandcastle` | `member`       | `is amy related to workspace:sandcastle as member?`         | Yes       |
| `david` | `workspace:sandcastle` | `member`       | `is david related to workspace:sandcastle as member?`       | No        |

##### 03. Updating the authorization model to include channels

So far, you have modeled the users' <ProductConcept section="what-is-a-relation" linkName="relations" /> to the workspace itself. In this task you will expand the model to include the relations concerning the channels.

By the end of it, you will run some queries to check whether a user can view or write to a certain channel. Queries such as:

- `is david related to channel:general as viewer?` (expected answer: No relation, as David is a guest user with only a relation to #proj-marketing-campaign)
- `is david related to channel:proj_marketing_campaign as viewer?` (expected answer: There is a relation, as there is a relation between David and #proj-marketing-campaign as a writer)
- `is bob related to channel:general as viewer?` (expected answer: There is a relation, as Bob is a member of the Sandcastle workspace, and all members of the workspace have a viewer relation to #general)

The requirements are:

- **Amy**, **Bob**, **Catherine** and **Emily**, are normal members of the Sandcastle workspace, they can **view** all **public channels**, in this case: #general and #proj-marketing-campaign
- **David**, a guest user, has only **view** and **write** access to the **#proj-marketing-campaign channel**
- **Bob** and **Emily** are the only ones with either **view** or **write** access to the **#marketing-internal channel**
- **Amy** and **Emily** are the only ones with **write** access to the **#general channel**

The possible relations to channels are:

- Workspace includes the channel, consider the relation that of a **parent workspace**
- A user can be a **viewer** and/or **writer** on a channel

The authorization model already has a section describing the workspace, what remains is describing the channel. That can be done by adding the following section to the configuration above:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type: 'channel', // A channel can have the following relations:
    relations: {
      parent_workspace: {
        // workspaces related to it as `parent_workspace`
        this: {},
      },
      writer: {
        // users related to it as `writer`
        this: {},
      },
      viewer: {
        // users related to it as `viewer`
        this: {},
      },
    },
    metadata: {
      relations: {
        parent_workspace: { directly_related_user_types: [{ type: 'workspace' }] },
        writer: {
          directly_related_user_types: [
            { type: 'user' },
            { type: 'workspace', relation: 'legacy_admin' },
            { type: 'workspace', relation: 'channels_admin' },
            { type: 'workspace', relation: 'member' },
            { type: 'workspace', relation: 'guest' },
          ],
        },
        viewer: {
          directly_related_user_types: [
            { type: 'user' },
            { type: 'workspace', relation: 'legacy_admin' },
            { type: 'workspace', relation: 'channels_admin' },
            { type: 'workspace', relation: 'member' },
            { type: 'workspace', relation: 'guest' },
          ],
        },
      },
    },
  }} skipVersion={true}
/>

:::info

The configuration snippet above describes a channel that can have the following relations:

- workspaces related to it as `parent_workspace`
- users related to it as `writer`
- users related to it as `viewer`

:::

###### Implied relation

There is an <ProductConcept section="what-are-direct-and-implied-relationships" linkName="implied relation" /> that anyone who can write to a channel can also read from it, so the authorization model can be modified to be:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type: 'channel',
    relations: {
      parent_workspace: {
        this: {},
      },
      writer: {
        this: {},
      },
      viewer: {
        // viewer is the union of the set of users with a direct viewer relation, and the set of users with writer relations
        union: {
          child: [
            {
              // a user can be assigned a direct `viewer` relation, i.e., not implied through another relation
              this: {},
            },
            {
              // a user that is a writer is also implicitly a viewer
              computedUserset: {
                relation: 'writer',
              },
            },
          ],
        },
      },
    },
    metadata: {
      relations: {
        parent_workspace: { directly_related_user_types: [{ type: 'workspace' }] },
        writer: {
          directly_related_user_types: [
            { type: 'user' },
            { type: 'workspace', relation: 'legacy_admin' },
            { type: 'workspace', relation: 'channels_admin' },
            { type: 'workspace', relation: 'member' },
            { type: 'workspace', relation: 'guest' },
          ],
        },
        viewer: {
          directly_related_user_types: [
            { type: 'user' },
            { type: 'workspace', relation: 'legacy_admin' },
            { type: 'workspace', relation: 'channels_admin' },
            { type: 'workspace', relation: 'member' },
            { type: 'workspace', relation: 'guest' },
          ],
        },
      },
    },
  }} skipVersion={true}
/>

:::info

Note that the channel type definition has been updated to indicate that viewer is the union of:

- the set of users with a <ProductConcept section="what-are-direct-and-implied-relationships" linkName="direct" /> viewer relation to this object
- the set of users with writer relations to this object

:::

As a result, the authorization model is:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'workspace',
        relations: {
          legacy_admin: {
            this: {},
          },
          channels_admin: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'legacy_admin',
                  },
                },
              ],
            },
          },
          member: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'channels_admin',
                  },
                },
                {
                  computedUserset: {
                    relation: 'legacy_admin',
                  },
                },
              ],
            },
          },
          guest: {
            this: {},
          },
        },
        metadata: {
          relations: {
            legacy_admin: { directly_related_user_types: [{ type: 'user' }] },
            channels_admin: { directly_related_user_types: [{ type: 'user' }] },
            member: { directly_related_user_types: [{ type: 'user' }] },
            guest: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'channel',
        relations: {
          parent_workspace: {
            this: {},
          },
          writer: {
            this: {},
          },
          viewer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'writer',
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            parent_workspace: { directly_related_user_types: [{ type: 'workspace' }] },
            writer: {
              directly_related_user_types: [
                { type: 'user' },
                { type: 'workspace', relation: 'legacy_admin' },
                { type: 'workspace', relation: 'channels_admin' },
                { type: 'workspace', relation: 'member' },
                { type: 'workspace', relation: 'guest' },
              ],
            },
            viewer: {
              directly_related_user_types: [
                { type: 'user' },
                { type: 'workspace', relation: 'legacy_admin' },
                { type: 'workspace', relation: 'channels_admin' },
                { type: 'workspace', relation: 'member' },
                { type: 'workspace', relation: 'guest' },
              ],
            },
          },
        },
      },
    ],
  }}
/>

###### Updating relationship tuples

What remains is to add the <ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuples" /> to indicate the relation between the users, workspace and the channels.

The Sandcastle workspace is a parent workspace of the #general, #marketing-internal and #proj-marketing-campaign channels.

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'workspace:sandcastle',
      relation: 'parent_workspace',
      object: 'channel:general',
    },
    {
      user: 'workspace:sandcastle',
      relation: 'parent_workspace',
      object: 'channel:marketing_internal',
    },
    {
      user: 'workspace:sandcastle',
      relation: 'parent_workspace',
      object: 'channel:proj_marketing_campaign',
    },
  ]}
/>

###### `#general` channel

The `#general` channel is a public channel visible to all the members of the workspace. In <ProductName format={ProductNameFormat.ShortForm}/>, you represent this relation in the form of the following relationship tuple:

<WriteRequestViewer
  relationshipTuples={[
    {
      _description:
        'The set of users related to `workspace:sandcastle` as member are also related to `channel:general` as `viewer`',
      user: 'workspace:sandcastle#member',
      relation: 'viewer',
      object: 'channel:general',
    },
  ]}
/>

:::info
This indicates The set of users related to `workspace:sandcastle` as member are also related to `channel:general` as `viewer`
:::

And to indicate that Amy and Emily can write to it:

<WriteRequestViewer
  relationshipTuples={[
    {
      _description:
        'Due to the configuration update you added earlier, writer relation is enough to imply a viewer relation',
      user: 'user:amy',
      relation: 'writer',
      object: 'channel:general',
    },
    {
      user: 'user:emily',
      relation: 'writer',
      object: 'channel:general',
    },
  ]}
/>

###### `#marketing-internal` channel

The `#marketing-internal` is visible to only Bob and Emily. They can view and write in it.

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:bob',
      relation: 'writer',
      object: 'channel:marketing_internal',
    },
    {
      user: 'user:emily',
      relation: 'writer',
      object: 'channel:marketing_internal',
    },
  ]}
/>

###### `#proj-marketing-campaign` channel

The `#proj-marketing-campaign` is public to all members of the Sandcastle workspace. They can view and write in it.

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'workspace:sandcastle#member',
      relation: 'writer',
      object: 'channel:proj_marketing_campaign',
    },
  ]}
/>

David is a guest user who can also view and write to #proj-marketing-campaign

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:david',
      relation: 'writer',
      object: 'channel:proj_marketing_campaign',
    },
  ]}
/>

###### Verification

Now that you have added the necessary relationship tuples, you will check to make sure that your configuration is valid.

First, we want to ensure david is not related to channel:general as viewer.

<CheckRequestViewer user={'user:david'} relation={'viewer'} object={'channel:general'} allowed={false} />

David should be related to channel:proj_marketing_campaign as viewer.

<CheckRequestViewer user={'user:david'} relation={'viewer'} object={'channel:proj_marketing_campaign'} allowed={true} />

Repeat this for the following relations

| User    | Object                            | Relation         | Query                                                            | Relation? |
| ------- | --------------------------------- | ---------------- | ---------------------------------------------------------------- | --------- |
| `amy`   | `workspace:sandcastle`            | `legacy_admin`   | `is amy related to workspace:sandcastle as legacy_admin?`        | Yes       |
| `amy`   | `workspace:sandcastle`            | `member`         | `is amy related to workspace:sandcastle as member?`              | Yes       |
| `amy`   | `workspace:sandcastle`            | `channels_admin` | `is amy related to workspace:sandcastle as channels_admin?`      | Yes       |
| `amy`   | `channel:general`                 | `writer`         | `is amy related to channel:general as writer?`                   | Yes       |
| `amy`   | `channel:general`                 | `viewer`         | `is amy related to channel:general as viewer?`                   | Yes       |
| `amy`   | `channel:marketing_internal`      | `writer`         | `is amy related to channel:marketing_internal as writer?`        | No        |
| `amy`   | `channel:marketing_internal`      | `viewer`         | `is amy related to channel:marketing_internal as viewer?`        | No        |
| `emily` | `channel:marketing_internal`      | `writer`         | `is emily related to channel:marketing_internal as writer?`      | Yes       |
| `emily` | `channel:marketing_internal`      | `viewer`         | `is emily related to channel:marketing_internal as viewer?`      | Yes       |
| `david` | `workspace:sandcastle`            | `guest`          | `is david related to workspace:sandcastle as guest?`             | Yes       |
| `david` | `workspace:sandcastle`            | `member`         | `is david related to workspace:sandcastle as member?`            | No        |
| `david` | `channel:general`                 | `viewer`         | `is david related to channel:general as viewer?`                 | No        |
| `david` | `channel:marketing_internal`      | `viewer`         | `is david related to channel:marketing_internal as viewer?`      | No        |
| `david` | `channel:proj_marketing_campaign` | `viewer`         | `is david related to channel:proj_marketing_campaign as viewer?` | Yes       |

#### Summary

- Have a basic understanding of <IntroductionSection linkName="authorization" section="authentication-and-authorization"/> and <ProductConcept/>.
- Understand how to model authorization for a communication platform like Slack using <ProductName format={ProductNameFormat.ProductLink}/>.

In this tutorial, you:

- were introduced to <IntroductionSection linkName="fine grain authentication" section="what-is-fine-grained-authorization"/> and <ProductName format={ProductNameFormat.ProductLink}/>.
- learned how to build and test an <ProductName format={ProductNameFormat.LongForm}/> authorization model for a communication platforms like Slack.

Upcoming tutorials will dive deeper into <ProductName format={ProductNameFormat.LongForm}/>, introducing concepts that will improve on the model you built today, and tackling different permission systems, with other relations and requirements that need to be met.

<Playground title="Slack" preset="slack" example="Slack" store="slack" />

If you are interested in learning more about Authorization and Role Management at Slack, check out the Auth0 Fine-Grained Authorization (FGA) team's chat with the Slack engineering team.
<!-- markdown-link-check-disable -->
<figure className="video_container">
  <iframe
    style={{ marginTop: 36, borderRadius: 8 }}
    width="100%"
    height="500"
    src={`https://www.youtube-nocookie.com/embed/-iVBsagaK5Y`}
    frameBorder="0"
    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
    allowFullScreen
  />
</figure>
<!-- markdown-link-check-enable -->

##### Exercises for you

- Try adding more relationship tuples to represent other users and channels being added. Then run queries to make sure that the authorization model remains valid.
- Update the configuration to model more Slack permissions (workspace owners, Slack orgs), then add the relationship tuples necessary and run some queries to validate your configuration.


<!-- End of openfga/openfga.dev/docs/content/modeling/advanced/slack.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/building-blocks/concentric-relationships.mdx -->

---
sidebar_position: 4
description: 'Modeling Concepts: Concentric Relationships'
slug: /modeling/building-blocks/concentric-relationships
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  Playground,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  RelationshipTuplesViewer,
} from '@components/Docs';

### Concentric Relationships

<DocumentationNotice />

In this short guide, you'll learn how to represent a concentric <ProductConcept section="what-is-a-relationship" linkName="relationships" />.

For example, if you want to have all editors of a document also be viewers of said document.

<CardBox title="When to use" appearance="filled">

Concentric relations make the most sense when your domain logic has nested relations, where one having relation implies having another relation.

For example:

- all `editors` are `viewers`
- all `managers` are `members`
- all `device_managers` are `device_renamers`

This allows you to only create a single _relationship tuple_ rather than creating n _relationship tuples_ for each relation.

</CardBox>

#### Before You Start

To better understand this guide, you should be familiar with some <ProductConcept /> and know how to develop the things listed below.

<details>
<summary>

You will start with the _<ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />_ below, it represents a `document` _<ProductConcept section="what-is-a-type" linkName="type" />_ that can have users **<ProductConcept section="what-is-a-relation" linkName="related" />** as `editor` and `viewer`.

Let us also assume that we have a `document` called "meeting_notes.doc" and bob is assigned as editor to this document.

</summary>

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          viewer: {
            this: {},
          },
          editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            viewer: { directly_related_user_types: [{type: 'user'}] },
            editor: { directly_related_user_types: [{type: 'user'}] },
          },
        },
      },
    ],
  }}
/>

The current state of the system is represented by the following relationship tuples being in the system already:

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      user: 'user:bob',
      relation: 'editor',
      object: 'document:meeting_notes.doc',
    },
  ]}
/>

<hr />

In addition, you will need to know the following:

##### Modeling User Groups

You need to know how to add users to groups and grant groups access to resources. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../user-groups.mdx)

##### <ProductName format={ProductNameFormat.ShortForm}/> concepts

- A <ProductConcept section="what-is-a-type" linkName="Type" />: a class of objects that have similar characteristics
- A <ProductConcept section="what-is-a-user" linkName="User" />: an entity in the system that can be related to an object
- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: is a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system
- An <ProductConcept section="what-is-an-object" linkName="Object" />: represents an entity in the system. Users' relationships to it can be define through relationship tuples and the authorization model
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a grouping consisting of a user, a relation and an object stored in <ProductName format={ProductNameFormat.ShortForm}/>

</details>

<Playground />

#### Step by step

With the current type definition, there isn't a way to indicate that all `editors` of a certain `document` are also automatically `viewers` of that document. So for a certain user, in order to indicate that they can both `edit` and `view` a certain `document`, two _<ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuples" />_ need to be created (one for `editor`, and another for `viewer`).

##### 01. Modify our model to imply editor as viewer

Instead of creating two _relationship tuples_, we can leverage concentric relationships by defining editors are viewers.

Our authorization model becomes the following:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          viewer: {
            union: {
              child: [
                {
                  // a user can be assigned a direct `viewer` relation, i.e., not implied through another relation
                  this: {},
                },
                {
                  // a user that is an editor is also implicitly a viewer
                  computedUserset: {
                    relation: 'editor',
                  },
                },
              ],
            },
          },
          editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            viewer: { directly_related_user_types: [{type: 'user'}] },
            editor: { directly_related_user_types: [{type: 'user'}] },
          },
        },
      },
    ],
  }}
/>

:::info

`viewer` of a `document` are any of:

1. users that are directly assigned as `viewer`
2. users that have `editor` of the document

:::

With this authorization model change, having an `editor` relationship with a certain document implies having a `viewer` relationship with that same document.

##### 02. Check that editors are viewers

Since we had a _relationship tuple_ that indicates that **bob** is an `editor` of **document:meeting_notes.doc**, this means **bob** is now implicitly a `viewer` of **document:meeting_notes.doc**.
If we now check: **is bob a viewer of document:meeting_notes.doc?** we would get the following:

<CheckRequestViewer user={'user:bob'} relation={'viewer'} object={'document:meeting_notes.doc'} allowed={true} />

:::caution Note
When creating relationship tuples for <ProductName format={ProductNameFormat.ShortForm}/> make sure to use unique ids for each object and user within your application domain. We're using first names and simple ids to just illustrate an easy-to-follow example.
:::

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how concentric relationships can be used."
  relatedLinks={[
    {
      title: 'Modeling Google Drive',
      description: 'See how to indicate that editors are commenters and viewers in Google Drive.',
      link: '../advanced/gdrive#01-individual-permissions',
      id: '../advanced/gdrive.mdx#01-individual-permissions',
    },
    {
      title: 'Modeling GitHub',
      description: 'See how to indicate that repository admins are writers and readers in GitHub.',
      link: '../advanced/github#01-permissions-for-individuals-in-an-org',
      id: '../advanced/github.mdx#01-permissions-for-individuals-in-an-org',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/building-blocks/concentric-relationships.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/building-blocks/direct-relationships.mdx -->

---
sidebar_position: 8
description: 'Modeling Concepts: Direct Relationships'
slug: /modeling/building-blocks/direct-relationships
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  Playground,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelationshipTuplesViewer,
  RelatedSection,
} from '@components/Docs';
import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

### Direct Relationships

<DocumentationNotice />

In this guide you'll learn how to model relationships that may or may not be assigned directly to individual users.

<CardBox title="When to use" appearance="filled">

Disabling _direct relationships_ for a certain relation on an objects are useful especially in cases where you are trying to model some permissions that are not usually granted individually to a user.

This is useful when:

- For security reason, not permitting permissions assigned directly to individuals without associating roles

</CardBox>

#### Before you start

To better understand this guide, you should be familiar with some <ProductConcept /> and know how to develop the things listed below.

<details>

<summary>

You will need to know the following:

- Direct Access
- <ProductName format={ProductNameFormat.ShortForm} /> Concepts

</summary>

##### Direct access

You need to know how to create an authorization model and create a relationship tuple to grant a user access to an object. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../direct-access.mdx)

##### <ProductName format={ProductNameFormat.ShortForm}/> Concepts

- A <ProductConcept section="what-is-a-type" linkName="Type" />: a class of objects that have similar characteristics
- A <ProductConcept section="what-is-a-user" linkName="User" />: an entity in the system that can be related to an object
- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: is a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system
- An <ProductConcept section="what-is-an-object" linkName="Object" />: represents an entity in the system. Users' relationships to it can be define through relationship tuples and the authorization model
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a grouping consisting of a user, a relation and an object stored in <ProductName format={ProductNameFormat.ShortForm}/>
- [Direct Relationship Type Restrictions](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx#direct-relationship-type-restrictions): used in the context of the relation definition can be used to allow direct relationships to the objects of this type

</details>

<Playground />

#### What are direct relationships?

Direct relationships are relationships where a user has a relationship to an <ProductConcept section="what-is-an-object" linkName="object" /> that is not dependent on any other relationship they have with that object.

When checking for a relationship, a direct relationship exists if a _<ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuple" />_ is present in the system with the exact same object and relation that were in the query and where the user is one of:

- the same user ID as that in the query
- type bound public access (`<type>:*`)
- a set of users that contains the user ID present in the query

#### Enable or disable direct relationships

Direct relationships can be enabled for a specific relation on an _<ProductConcept section="what-is-a-type" linkName="object type" />_ by adding [direct relationship type restrictions](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx#direct-relationship-type-restrictions) from that <ProductConcept section="what-is-a-relation-definition" linkName="relation's definition" />. Likewise, they can be disabled by removing the direct relationship type restrictions.

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          viewer: {
            union: {
              child: [
                {
                  // a user can be assigned a direct `viewer` relation, i.e., not implied through another relation
                  this: {},
                },
                {
                  // a user who is related as an editor is also implicitly related as a viewer
                  computedUserset: {
                    relation: 'editor',
                  },
                },
              ],
            },
          },
          editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            viewer: {
              directly_related_user_types: [
                { type: 'user' },
                { type: 'user', wildcard: {} },
                { type: 'team', relation: 'member' },
              ],
            },
            editor: { directly_related_user_types: [{ type: 'user' }, { type: 'team', relation: 'member' }] },
          },
        },
      },
      {
        type: 'team',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

:::info

The <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" /> describes two <ProductConcept section="what-is-a-type" linkName="object types" />: `document` and `team`.

The `document` <ProductConcept section="what-is-a-type-definition" linkName="type definition" /> has two <ProductConcept section="what-is-a-relation" linkName="relations" />, `editor` and `viewer`. Both relations allow a direct relationship; `viewer` also allows an <ProductConcept section="what-are-direct-and-implied-relationships" linkName="indirect relationship" /> through `editor`.

In the `team` type definition, there is a single `member` relation that only allows direct relationships.

:::

#### How it affects your system

To illustrate the effect enabling or disabling direct relationships on a specific relation has, we'll investigate several situations.

##### 1. With direct relationships enabled

Let us start with the authorization model we had above:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          viewer: {
            union: {
              child: [
                {
                  // a user can be assigned a direct `viewer` relation, i.e., not implied through another relation
                  this: {},
                },
                {
                  // a user who is related as an editor is also implicitly related as a viewer
                  computedUserset: {
                    relation: 'editor',
                  },
                },
              ],
            },
          },
          editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            viewer: {
              directly_related_user_types: [
                { type: 'user' },
                { type: 'user', wildcard: {} },
                { type: 'team', relation: 'member' },
              ],
            },
            editor: { directly_related_user_types: [{ type: 'user' }, { type: 'team', relation: 'member' }] },
          },
        },
      },
      {
        type: 'team',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

Now choose the type of relation to see how it affects your system:

<Tabs groupId='relationship-type'>
<TabItem value='direct' label='Direct User'>

Assume you have a tuple that states that Anne is a `viewer` of `document:planning`

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      user: 'user:anne',
      relation: 'viewer',
      object: 'document:planning',
    },
  ]}
/>

Now if we do a <ProductConcept section="what-is-a-check-request" linkName="check request" /> to see if Anne can view the planning document, we will get a response of `{"allowed": true}`.

<CheckRequestViewer user={'user:anne'} relation={'viewer'} object={'document:planning'} allowed={true} />

This is because:

- There is a relationship tuple specifying that Anne has a `viewer` relationship with `document:planning`.
- Direct relationships are allowed in the `viewer` relation definition in the `document` type definition.

</TabItem>

<TabItem value='everyone' label='Type Bound Public Access'>

Assume you have a <ProductConcept section="what-is-type-bound-public-access" linkName="type bound public access" /> tuple where everyone of type `user` is a `viewer` of `document:planning` (In other words, the document is public)

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      user: 'user:*',
      relation: 'viewer',
      object: 'document:planning',
    },
  ]}
/>

Now if we do a check request to see if Beth can view the planning document, we will get a response of `{"allowed": true}`.

<CheckRequestViewer user={'user:beth'} relation={'viewer'} object={'document:planning'} allowed={true} />

This is because:

- There is a relationship tuple specifying that everyone of type `user` has a `viewer` relationship with `document:planning`.
- Direct relationships are allowed in the `viewer` relation definition in the `document` type definition.

:::info

Note: Even though the relationship tuple stored in the system does not specify the user (`beth`), this is still considered a direct relationship.

:::

</TabItem>

<TabItem value='userset' label='Userset'>

[_Usersets_](https://github.com/openfga/openfga.dev/blob/main/./usersets.mdx) are the third way direct relationships apply, we will see how in this section.

Assume you have two relationship tuples:

- Charlie is a member of the product team.
- Members of the product team are viewers of the planning document.

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      user: 'user:charlie',
      relation: 'member',
      object: 'team:product',
    },
    {
      user: 'team:product#member',
      relation: 'viewer',
      object: 'document:planning',
    },
  ]}
/>

:::info

Note that these two relationship tuples are specifying that:

- `user:charlie` is a `member` of `team:product`.
- any `member` of `team:product` is a `viewer` of `document:planning`.
  - Note that this second relationship tuple is specifying that the **members** of the team have viewer access, and not the team object itself.

:::

Now if we do a _check request_ to see if charlie can view the planning document, we will get a response of `{"allowed": true}`.

<CheckRequestViewer user={'user:charlie'} relation={'viewer'} object={'document:planning'} allowed={true} />

This is because:

- Charlie is a member of the product team.
- There is a relationship tuple specifying that all members of the product team have a `viewer` relationship with `document:planning`.
- Direct relationships are allowed in the `viewer` relation definition in the `document` type definition.

Note that this is still considered a direct relationship no matter how many resolutions occur on the usersets until the user is found.

For example, if our relationship tuples were the following relationship tuples:

- Dany is a member of the product leads team.
- Members of the product leads team are members of the product team.
- Members of the product team are members of the contoso team.
- Members of the contoso team are viewers of the planning document.

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      user: 'user:dany',
      relation: 'member',
      object: 'team:product-leads',
    },
    {
      user: 'team:product-leads#member',
      relation: 'member',
      object: 'team:product',
    },
    {
      user: 'team:product#member',
      relation: 'member',
      object: 'team:contoso',
    },
    {
      user: 'team:contoso#member',
      relation: 'viewer',
      object: 'document:planning',
    },
  ]}
/>

A subsequent _check request_ to see if Dany can view the planning document will still return a response of `{"allowed": true}`.

<CheckRequestViewer user={'user:dany'} relation={'viewer'} object={'document:planning'} allowed={true} />

:::info

Note: Even though the relationship tuple stored in the system does not specify the user (`charlie` or `dany`), this is still considered a direct relationship.

:::

</TabItem>

<TabItem value='indirect' label='Indirect Relationship'>

Here we will cover one example of an <ProductConcept section="what-are-direct-and-implied-relationships" linkName="indirect relationship" /> in order to see how they differ from direct relationships.

With the same authorization model we have above, assume there is a relationship tuple that specifies that Emily is an `editor` of `document:planning`.

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      user: 'user:emily',
      relation: 'editor',
      object: 'document:planning',
    },
  ]}
/>

A subsequent _check request_ to see if emily can view the planning document will still return a response of `{"allowed": true}`.

<CheckRequestViewer user={'user:emily'} relation={'viewer'} object={'document:planning'} allowed={true} />

This is because:

- Emily is an `editor` of the planning document.
- The authorization model specified that anyone who is an `editor` on a `document` is also a `viewer` on that document.

In this case, there is **NO** direct viewer relationship between Emily and the planning document. The only viewer relationship that exists is implied because Emily is an editor and the authorization model specified that any document's `editor` is that document's viewer.

</TabItem>
</Tabs>

##### 2. With direct relationships disabled

In this section, we will investigate the effect of disabling _direct relationships_ on the document's `viewer` relation.

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          viewer: {
            // a user who is related as an editor is also implicitly related as a viewer
            computedUserset: {
              relation: 'editor',
            },
          },
          editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            editor: { directly_related_user_types: [{ type: 'user' },{ type: 'team', relation: 'member' }] },
          },
        },
      },
      {
        type: 'team',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

:::info

Notice that in this updated authorization model, the direct relationship keyword has been removed from the document's `viewer` relation definition.

:::

Now choose the type of relation to see how it affects your system:

<Tabs groupId='relationship-type'>
<TabItem value='direct' label='Direct User'>

Assume you have a tuple that states that Fred is a `viewer` of `document:planning`

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      user: 'user:fred',
      relation: 'viewer',
      object: 'document:planning',
    },
  ]}
/>

Now if we do a check request to see if Fred can view the planning document, we will get a response of `{"allowed": false}`.

<CheckRequestViewer user={'user:fred'} relation={'viewer'} object={'document:planning'} allowed={false} />

This is because:

- Even though there is a relationship tuple specifying that Fred has a `viewer` relationship with `document:planning`.
- Direct relationships are **NOT** allowed in the `viewer` relation definition in the `document` type definition.

</TabItem>

<TabItem value='everyone' label='Everyone'>

You will see the same behaviour with a relationship tuple specifying everyone of type `user` as the user.

Assume you have a tuple that states that everyone of type `user` is a `viewer` of `document:planning`.

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      user: 'user:*',
      relation: 'viewer',
      object: 'document:planning',
    },
  ]}
/>

Now if we do a check request to see if Gabriel can view the planning document, we will get a response of `{"allowed": false}`.

<CheckRequestViewer user={'user:gabriel'} relation={'viewer'} object={'document:planning'} allowed={false} />

This is because:

- Even though there is a relationship tuple specifying that everyone has a `viewer` relationship with `document:planning`.
- Direct relationships are **NOT** allowed in the `viewer` relation definition in the `document` type definition.

</TabItem>

<TabItem value='userset' label='Userset'>

The same logic applies to usersets.

Assume you have two relationship tuples:

- Henry is a member of the product team.
- Members of the product team are viewers of the planning document.

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      user: 'user:henry',
      relation: 'member',
      object: 'team:product',
    },
    {
      user: 'team:product#member',
      relation: 'viewer',
      object: 'document:planning',
    },
  ]}
/>

Now if we do a check request to see if Henry can view the planning document, we will get a response of `{"allowed": false}`.

<CheckRequestViewer user={'user:henry'} relation={'viewer'} object={'document:planning'} allowed={false} />

This is because although:

- Henry is a member of the product team.
- There is a relationship tuple specifying that all members of the product team have a `viewer` relationship with `document:planning`.

Direct relationships are **NOT** allowed in the `viewer` relation definition in the `document` type definition.

</TabItem>

<TabItem value='indirect' label='Indirect Relationship'>

Indirect relationships are not affected by disabling a direct relationship on a certain relation.

Assume there is a relationship tuple that specifies that Ingred is an `editor` of `document:planning`.

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      user: 'user:ingred',
      relation: 'editor',
      object: 'document:planning',
    },
  ]}
/>

A subsequent check request to see if Ingred can view the planning document will still return a response of `{"allowed": true}`.

<CheckRequestViewer user={'user:ingred'} relation={'viewer'} object={'document:planning'} allowed={true} />

</TabItem>
</Tabs>

#### Related Sections

<RelatedSection
  description="Check the following sections for more one how direct relationships can be used. Also, take a look at the access relation in the feature type in Modeling Entitlements for another use-case."
  relatedLinks={[
    {
      title: 'Modeling Roles and Permissions',
      description: 'Learn how to remove the direct relationship to indicate nonassignable permissions.',
      link: '../roles-and-permissions',
      id: '../roles-and-permissions.mdx',
    },
    {
      title: 'Modeling for IoT',
      description: 'See how Roles and Permissions can be used in an IoT use-case.',
      link: '../advanced/iot',
      id: '../advanced/iot.mdx',
    },
    {
      title: 'Modeling Entitlements',
      description:
        'Take a look at the access relation in the feature type for an example of removing the direct relationship',
      link: '../advanced/entitlements',
      id: '../advanced/entitlements.mdx',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/building-blocks/direct-relationships.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/building-blocks/object-to-object-relationships.mdx -->

---
sidebar_position: 2
slug: /modeling/building-blocks/object-to-object-relationships
description: Modeling relationships between objects (e.g. folder parent of a document)
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  Playground,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  WriteRequestViewer,
} from '@components/Docs';

### Object to Object Relationships

<DocumentationNotice />

In this guide you'll learn how to model your application with <ProductConcept section="what-is-an-object" linkName="objects" /> that are not specifically tied to a user. For example, a `folder` is a `parent` of a `document`.

<CardBox title="When to use" appearance="filled">

This design pattern is helpful in the case where there are relationships between different objects. With <ProductName format={ProductNameFormat.LongForm}/>, so long as both objects are in a type defined in the <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />, relationship tuples can be added to indicate a relationship between them.

For example:

- `communities` can contain `channels`
- `channels` can contain `posts`
- `channels` can contain `threads`
- `threads` can contain `posts`
- `bookshelf` can have `books`
- `trips` can have `bookings`
- `account` can contain `transactions`
- `buildings` can have `doors`

</CardBox>

#### Before you start

To better follow this guide, make sure you're familiar with some <ProductConcept /> and know how to develop the things listed below.

<details>
<summary>

You will start with the _<ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />_ below, it represents a `document` _<ProductConcept section="what-is-a-type" linkName="type" />_ that can have users **<ProductConcept section="what-is-a-relation" linkName="related" />** as `editor`, and `folder` type that can have users related as `viewer`.

</summary>

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            editor: { directly_related_user_types: [{type: 'user'}] },
          },
        },
      },
      {
        type: 'folder',
        relations: {
          viewer: {
            this: {},
          },
        },
        metadata: {
          relations: {
            viewer: { directly_related_user_types: [{type: 'user'}] },
          },
        },
      },
    ],
  }}
/>

<hr />

In addition, you will need to know the following:

##### Modeling user groups

You need to know how to add users to groups and grant groups access to resources. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/../user-groups.mdx)

##### <ProductName format={ProductNameFormat.ShortForm}/> concepts

- A <ProductConcept section="what-is-a-type" linkName="Type" />: a class of objects that have similar characteristics
- A <ProductConcept section="what-is-a-user" linkName="User" />: an entity in the system that can be related to an object
- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: is a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system
- An <ProductConcept section="what-is-an-object" linkName="Object" />: represents an entity in the system. Users' relationships to it can be define through relationship tuples and the authorization model
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a grouping consisting of a user, a relation and an object stored in <ProductName format={ProductNameFormat.ShortForm}/>

</details>

<Playground />

#### Step by step

##### 01. Create parent relations in document

To represent that a `folder` can be a `parent` of a `document`, we first need to modify our `document` <ProductConcept section="what-is-a-type-definition" linkName="type definition" /> to allow a `parent` <ProductConcept section="what-is-a-relation" linkName="relation" />.

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          // allow others to have 'parent' relation to 'document'
          parent: {
            this: {},
          },
          editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            parent: { directly_related_user_types: [{type: 'folder'}] },
            editor: { directly_related_user_types: [{type: 'user'}] },
          },
        },
      },
      {
        type: 'folder',
        relations: {
          viewer: {
            this: {},
          },
        },
        metadata: {
          relations: {
            viewer: { directly_related_user_types: [{type: 'user'}] },
          },
        },
      },
    ],
  }}
/>

##### 02. Add Parent Relationship Tuples

Once the type definition is updated, we can now create the <ProductConcept section="what-is-a-relationship" linkName="relationship" /> between a `folder` as a `parent` of a `document`. To do this, we will create a new **<ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuple" />** that describes: **folder:budgets** is a `parent` of **document:may_budget.doc**. In <ProductName format={ProductNameFormat.LongForm}/>, <ProductConcept section="what-is-a-user" linkName="users" /> in the relationship tuples can not only be IDs, but also other objects in the form of `type:object_id`.

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'The user in this case is another object where the type is `folder` and the object_id is `budgets`',
      user: 'folder:budgets',
      relation: 'parent',
      object: 'document:may_budget.doc',
    },
  ]}
/>

##### 03. Check that parent folders have permissions

Once that relationship tuple is added to <ProductName format={ProductNameFormat.ShortForm}/>, we can <ProductConcept section="what-is-a-check-request" linkName="check" /> if the relationship is valid by asking the following: **"is folder:budgets a parent of document:may_budget.doc?"**

<CheckRequestViewer user={'folder:budgets'} relation={'parent'} object={'document:may_budget.doc'} allowed={true} />

It is important to note that the current authorization model does not imply inheritance of permissions. Even though **folder:budgets** is a `parent` of **document:may_budget.doc**, **it does not inherit the `editor` relation from `parent` to `document`.** Meaning `editors` on **folder:budgets** are not `editors` on **document:may_budget.doc**. Further configuration changes are needed to indicate that and will be tackled in a later guide.

:::caution
When creating relationship tuples for <ProductName format={ProductNameFormat.ShortForm}/> make sure to use unique ids for each object and user within your application domain. We are using first names and simple ids to just illustrate an easy-to-follow example.
:::

#### Advanced object to object relationships

Object to object can be used for more advanced use case, such as [entitlements](https://github.com/openfga/openfga.dev/blob/main/../advanced/entitlements.mdx). An example use case is to allow subscribers to be entitled to different plans.

##### 01. Create authorization model with object to object relationships

To do this, the authorization model will have two <ProductConcept section="what-is-a-type" linkName="types" /> - feature and plan.

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'feature',
        relations: {
          associated_plan: {
            this: {},
          },
          access: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      relation: 'associated_plan',
                    },
                    computedUserset: {
                      relation: 'subscriber_member',
                    },
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            associated_plan: { directly_related_user_types: [{type: 'plan'}] },
            access: { directly_related_user_types: [{type: 'user'}] },
          },
        },
      },
      {
        type: 'plan',
        relations: {
          subscriber_member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            subscriber_member: { directly_related_user_types: [{type: 'user'}] },
          },
        },
      },
    ],
  }}
/>

Type `feature` has two relations, associated_plan and access. Relation `associated_plan` allows associating plans with features while `access` defines who can access the feature. In our case, the access can be achieved either from

- <ProductConcept section="what-are-direct-and-implied-relationships" linkName="direct relationship" /> via  [direct relationship type restrictions](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx#direct-relationship-type-restrictions).
  or `this`
- object to object relationship where a user can access because it is a subscriber_member of a particular plan AND that plan is associated with the feature.

Here, we define `plan` as the user of object `feature` with relationship `associated_plan` rather than defining `feature` as the user of object `plan` with relationship `feature`. The reason we choose the former is that we want to describe our system in the following [plain language](https://github.com/openfga/openfga.dev/blob/main/../getting-started.mdx#write-it-in-plain-language):

<CardBox monoFontChildren appearance='filled'>

- A user can access a feature in a plan if they are a subscriber member of a plan that is the associated plan of a feature.

</CardBox>

This will give us a flow of user->organization->plan->feature and allows us to answer the question of whether user can access a feature rather than whether user is subscriber of a plan.

##### 02. Adding relationship tuples

To realize the relationship, we will need to add the following relationship tuples.

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'make anne as subscriber_member for plan:advanced',
      user: 'user:anne',
      relation: 'subscriber_member',
      object: 'plan:advanced',
    },
    {
      _description: 'The advanced plan is associated with the data preview feature',
      user: 'plan:advanced',
      relation: 'associated_plan',
      object: 'feature:data_preview',
    },
  ]}
/>

##### 03. Check to see if access is allowed without direct relationship

To validate that the authorization model and relationship tuples are correct, we can ask the question:

<CheckRequestViewer user={'user:anne'} relation={'access'} object={'feature:data_preview'} allowed={true} />

We see that `anne` is allowed to `access` `feature:data_preview` without requiring direct relationship.

##### 04. Disassociating plan from feature

At any point in time, `plan:advanced` may be disassociated from `feature:data_preview`.

<WriteRequestViewer
  deleteRelationshipTuples={[
    {
      _description: 'Remove advanced plan from data preview feature',
      user: 'plan:advanced',
      relation: 'associated_plan',
      object: 'feature:data_preview',
    },
  ]}
/>

When this is the case, `anne` will no longer have `access` to `feature:data_preview` even though she is still a `subscriber_member` of `plan:advanced`.

<CheckRequestViewer user={'user:anne'} relation={'access'} object={'feature:data_preview'} allowed={false} />
<CheckRequestViewer user={'user:anne'} relation={'subscriber_member'} object={'plan:advanced'} allowed={true} />

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how object-to-object relationships can be used."
  relatedLinks={[
    {
      title: 'Advanced Modeling Patterns: Entitlements',
      description: 'Learn how to model entitlement access patterns.',
      link: '../advanced/entitlements',
      id: '../advanced/entitlements.mdx',
    },
    {
      title: 'Modeling Parent-Child Relationships',
      description: 'Learn how to model parent and child relationships.',
      link: '../parent-child',
      id: '../parent-child.mdx',
    },
    {
      title: 'Modeling User Groups',
      description: 'Learn how to model user groups.',
      link: '../user-groups',
      id: '../user-groups.mdx',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/building-blocks/object-to-object-relationships.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/building-blocks/overview.mdx -->

---
id: overview
title: 'Building Blocks'
slug: /modeling/building-blocks
sidebar_position: 0
---

import { DocumentationNotice, IntroCard, CardGrid } from '@components/Docs';

<DocumentationNotice />

This section has guides that on the building blocks of authorization model.

<IntroCard
  title="When to use"
  description="The content in this section is useful:"
  listItems={[
    `If you are starting with {ProductName} and want to learn the building blocks that can be used to build any model.`,
  ]}
/>

### Content

<CardGrid
  middle={[
    {
      title: 'Direct Relationships',
	  description: 'Learn to model relationships that may or may not be assigned directly to individual users.',
      to: 'building-blocks/direct-relationships',
    },
    {
      title: 'Concentric Relationships',
	  description: 'Learn to model nested relationships in your application.',
      to: 'building-blocks/concentric-relationships',
    },
    {
      title: 'Object to Object Relationships',
	  description: 'Learn to model your application with objects that are not specifically tied to a user.',
      to: 'building-blocks/object-to-object-relationships',
    },
    {
      title: 'Usersets',
	  description: 'Learn to model your application by assigning relationships to groups of users.',
      to: 'building-blocks/usersets',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/building-blocks/overview.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/building-blocks/usersets.mdx -->

---
sidebar_position: 2
slug: /modeling/building-blocks/usersets
description: Modeling with userset
---

import {
  AuthzModelSnippetViewer,
  CheckRequestViewer,
  DocumentationNotice,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  RelationshipTuplesViewer,
} from '@components/Docs';

### Usersets

<DocumentationNotice />

#### What is a userset?

A userset represents a set or collection of <ProductConcept section="what-is-a-user" linkName="users" />.

Usersets can be used to indicate that a group of users in the system have a certain <ProductConcept section="what-is-a-relation" linkName="relation" /> with an <ProductConcept section="what-is-an-object" linkName="object" />. This can be used to assign permissions to groups of users rather than specific ones, allowing us to represent the permissions in our system using less tuples and granting us flexibility in granting or denying access in bulk.

In <ProductName format={ProductNameFormat.ShortForm}/>, usersets are represented via this notation: `object#relation`, where <ProductConcept section="what-is-an-object" linkName="object" /> is made up of a <ProductConcept section="what-is-a-type" linkName="type" /> and an object identifier. For example:

- `company:xyz#employee` represents all users that are related to `company:xyz` as `employee`
- `tweet:12345#viewer` represents all users that are related to `tweet:12345` as `viewer`

#### How do check requests work with usersets?

Imagine the following authorization model:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'org',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'document',
        relations: {
          reader: {
            this: {},
          },
        },
        metadata: {
          relations: {
            reader: { directly_related_user_types: [{ type: 'user' }, { type: 'org', relation: 'member' }] },
          },
        },
      },
    ],
  }}
/>

Now let us assume that the store has the following tuples:

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      _description: 'Userset "Members of the xyz org" can read the budget document',
      user: 'org:xyz#member',
      relation: 'reader',
      object: 'document:budget',
    },
    {
      _description: 'Anne is part of the userset "Members of the xyz org"',
      user: 'user:anne',
      relation: 'member',
      object: 'org:xyz',
    },
  ]}
/>

If we call the <ProductConcept section="what-is-a-check-request" linkName="check API" /> to see if user `anne` has a `reader` relationship with `document:budget`, <ProductName format={ProductNameFormat.ShortForm}/> will check whether `anne` is part of the userset that does have a `reader` relationship. Because she is part of that userset, the request will return true:

<CheckRequestViewer user={'user:anne'} relation={'reader'} object={'document:budget'} allowed={true} />

#### How do expand requests work with usersets?

Imagine the following authorization model:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          writer: {
            this: {},
          },
          reader: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  // a user who is related as a writer is also implicitly related as a reader
                  computedUserset: {
                    relation: 'writer',
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            reader: { directly_related_user_types: [{ type: 'user' }, { type: 'org', relation: 'member' }] },
            writer: { directly_related_user_types: [{ type: 'user' }, { type: 'org', relation: 'member' }] },
          },
        },
      },
    ],
  }}
/>

If we wanted to see which users and usersets have a `reader` relationship with `document:budget`, we can call the [Expand API](https://github.com/openfga/openfga.dev/blob/main//api/service#Relationship%20Queries/Expand). The response will contain a userset tree where the leaf nodes are specific user IDs and usersets. For example:

```json
{
  "tree": {
    "root": {
      "type": "document:budget#reader",
      "union": {
        "nodes": [
          {
            "type": "document:budget#reader",
            "leaf": {
              "users": {
                "users": ["user:bob"]
              }
            }
          },
          {
            "type": "document:budget#reader",
            "leaf": {
              "computed": {
                "userset": "document:budget#writer"
              }
            }
          }
        ]
      }
    }
  }
}
```

As you can see from the response above, with usersets we can express [unions](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx#the-union-operator) of user groups. We can also express [intersections](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx#the-intersection-operator) and [exclusions](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx#the-exclusion-operator).

#### Internals

Using the type definitions in the authorization model, some of the situations we can represent are:

- that a user is **not** in a set of users having a certain relation to an object, even if a relationship tuple exists in the system. See [Disabling Direct Relationships](https://github.com/openfga/openfga.dev/blob/main/./direct-relationships.mdx#2-with-direct-relationships-disabled)
- that a user has a certain relationship with an object if they are in the [union](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx#the-union-operator), [intersection](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx#the-intersection-operator) or [exclusion](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx#the-exclusion-operator) of usersets.
- that a user being in a set of users having a certain relation to an object can result in them having another relation to the object. See [Concentric Relationships](https://github.com/openfga/openfga.dev/blob/main/./concentric-relationships.mdx)
- that the user being in a set of users having a certain relation to an object and that object is in a set of users having a certain relation to another object, can imply that the original user has a certain relationship to the final object. See [Object-to-Object Relationships](https://github.com/openfga/openfga.dev/blob/main/./object-to-object-relationships.mdx)

When executing the Check API of the form `check(user, relation, object)`, <ProductName format={ProductNameFormat.ShortForm}/> will perform the following steps:

1. In the authorization model, look up `type` and its `relation`. Start building a tree where the root node will be the definition of that `relation`, which can be a union, exclusion, or intersection of usersets, or it can be direct users.
1. Expand all the usersets involved into new nodes in the tree. This means recursively finding all the users that are members of the usersets. If there are direct relationships with users, create leaf nodes.
1. Check whether `user` is a leaf node in the tree. If the API finds one match, it will return immediately and will not expand the remaining nodes.

![Image showing the path <ProductName format={ProductNameFormat.ShortForm}/> traverses to find if a user is in the userset related to an object](https://github.com/openfga/openfga.dev/blob/main/./assets/usersets-check-tree.png)

#### Related Sections

<RelatedSection
  description="See the following sections for more information:"
  relatedLinks={[
    {
      title: 'Managing Group Membership',
      description: 'How to add users to a userset',
      link: '../../interacting/managing-group-membership',
      id: '../../interacting/managing-group-membership.mdx',
    },
    {
      title: 'Managing Group Access',
      description: 'How to add permissions to a userset',
      link: '../../interacting/managing-group-access',
      id: '../../interacting/managing-group-access.mdx',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/building-blocks/usersets.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/migrating/migrating-models.mdx -->

---
title: 'Model Migrations'
slug: /modeling/migrating/migrating-models
sidebar_position: 1
---

import { DocumentationNotice, ProductName, ProductNameFormat, IntroCard, CardGrid, CardBox} from '@components/Docs';

<DocumentationNotice />

You can think of model migrations for <ProductName format={ProductNameFormat.ShortForm}/> in the same way as you think about relational database migrations. You can perform migrations with or without downtime for both, and for some changes, doing them without downtime is harder.

| <ProductName format={ProductNameFormat.ShortForm}/> | Relational Databases  |
|-----------------------------------------------------|-----------------------|
| Add a type                                          | Add a table           |
| Remove a type                                       | Remove a table        |
| Rename a type                                       | Rename a table        |
| Add a relation                                      | Add a nullable column |
| Rename a relation                                   | Rename a column       |
| Delete a relation                                   | Delete a column       |

When thinking about migrations, keep in mind that:

- [Models are immutable](https://github.com/openfga/openfga.dev/blob/main/../../getting-started/immutable-models.mdx).
- The tuples that are not valid according to the specified model, are ignored when evaluating queries.

#### To add a type or relation

  1. Add the type or relation to the authorization model, and write the model to the store. This will generate a new model ID.
  2. If you have tuples to write for the new types/relations, write them.
  3. Update the application code to start using those new types/relations.
  4. Configure the application to start using the new model ID.

#### To delete a type or relation

  1. Delete the type or relation to the authorization model, and write the model to the store. This will generate a new model ID.
  2. Update the application code to stops using the deleted types/relations.
  3. Configure the application to start using the new model ID.
  4. Delete the tuples for the deleted type/relations. While not required, doing so can improve performance. Invalid tuples will be ignored during query evaluation, but their presence may slow down the process if they need to be retrieved.

#### To rename a type or relation

  - [This document](https://github.com/openfga/openfga.dev/blob/main/./migrating-relations.mdx) describes an end-to-end example for that use case.



<!-- End of openfga/openfga.dev/docs/content/modeling/migrating/migrating-models.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/migrating/migrating-relations.mdx -->

---
sidebar_position: 1
slug: /modeling/migrating/migrating-relations
description: Migrating relations
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  ReadRequestViewer,
  RelatedSection,
  RelationshipTuplesViewer,
  WriteRequestViewer,
} from '@components/Docs';

### Migrating Relations

<DocumentationNotice />

In the lifecycle of software development, you will need to make updates or changes to the <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />. In this guide, you will learn best practices for changing your existing authorization model. With these recommendations, you will minimize downtime and ensure your relationship models stay up to date.

#### Before you start

This guide assumes you are familiar with the following OpenFGA concepts:

- A <ProductConcept section="what-is-a-type" linkName="Type" />: a class of objects that have similar characteristics
- A <ProductConcept section="what-is-a-user" linkName="User" />: an entity in the system that can be related to an object
- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: is a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system
- An <ProductConcept section="what-is-an-object" linkName="Object" />: represents an entity in the system. Users' relationships to it can be defined through relationship tuples and the authorization model
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a grouping consisting of a user, a relation and an object stored in <ProductName format={ProductNameFormat.ShortForm} />
- [Intersection Operator](https://github.com/openfga/openfga.dev/blob/main/../../configuration-language.mdx#the-intersection-operator): the intersection operator can be used to indicate a relationship exists if the user is in all the sets of users

#### Step by step

The document below is an example of a relational authorization model. In this model, you can assign users to the `editor` relation. The `editor` relation has write privileges that regular users do not.

In this scenario, you will migrate the following model:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'document',
        relations: {
          editor: {
            this: {},
          },
          can_edit: {
            computedUserset: {
              object: '',
              relation: 'editor',
            },
          },
        },
        metadata: {
          relations: {
            editor: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'user',
      },
    ],
  }}
/>

There are existing <ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuples" /> associated with editor relation.

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      user: 'user:anne',
      relation: 'editor',
      object: 'document:roadmap',
    },
    {
      user: 'user:charles',
      relation: 'editor',
      object: 'document:roadmap',
    },
  ]}
/>

This is the authorization model that you will want to migrate to:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'document',
        relations: {
          writer: {
            this: {},
          },
          can_write: {
            computedUserset: {
              object: '',
              relation: 'writer',
            },
          },
        },
        metadata: {
          relations: {
            writer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'user',
      },
    ],
  }}
/>

<hr />

##### 01. Create a backwards compatible model

To avoid service disruption, you will create a backwards compatible model. The backwards compatible model ensures the existing relationship tuple will still work.

In the example below, `user:Anne` still has write privileges to the `document:roadmap` resource.

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'document',
        relations: {
          editor: {
            this: {},
          },
          writer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'editor',
                  },
                },
              ],
            },
          },
          can_write: {
            computedUserset: {
              object: '',
              relation: 'writer',
            },
          },
          can_edit: {
            computedUserset: {
              object: '',
              relation: 'writer',
            },
          },
        },
        metadata: {
          relations: {
            editor: { directly_related_user_types: [{ type: 'user' }] },
            writer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'user',
      },
    ],
  }}
/>

Test the `can_edit` definition. It should produce a value of `true`.

<CheckRequestViewer user={'user:anne'} relation={'can_write'} object={'document:roadmap'} allowed={true} />
<CheckRequestViewer user={'user:anne'} relation={'can_edit'} object={'document:roadmap'} allowed={true} />

##### 02. Create a new relationship tuple

Now that you have a backwards compatible model, you can create new relationship tuples with a new relation.

In this example, you will add Bethany to the `writer` relationship.

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'Bethany assigned writer instead of editor',
      user: 'user:bethany',
      relation: 'writer',
      object: 'document:roadmap',
    },
  ]}
/>

Run a check in the API for Bethany to ensure correct access.

<CheckRequestViewer user={'user:bethany'} relation={'can_write'} object={'document:roadmap'} allowed={true} />

##### 03. Migrate the existing relationship tuples

Next, migrate the existing relationship tuples. The new relation makes this definition obsolete.

Use the `read` API to look up all relationship tuples.

<ReadRequestViewer
  tuples={[
    {
      user: 'user:anne',
      relation: 'editor',
      object: 'document:planning',
    },
    {
      user: 'user:charles',
      relation: 'editor',
      object: 'document:planning',
    },
  ]}
/>

Then filter out the tuples that do not match the object type or relation (in this case, `document` and `editor` respectively), and update the new tuples with the `write` relationship.

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:anne',
      relation: 'writer',
      object: 'document:roadmap',
    },
    {
      user: 'user:charles',
      relation: 'writer',
      object: 'document:roadmap',
    },
  ]}
/>

Finally, remove the old relationship tuples.

<WriteRequestViewer
  deleteRelationshipTuples={[
    {
      user: 'user:anne',
      relation: 'editor',
      object: 'document:roadmap',
    },
    {
      user: 'user:charles',
      relation: 'editor',
      object: 'document:roadmap',
    },
  ]}
/>

:::info
Perform a `write` operation before a `delete` operation to ensure Anne still has access.
:::

Confirm the tuples are correct by running a check on the user.

<CheckRequestViewer user={'user:anne'} relation={'can_write'} object={'document:roadmap'} allowed={true} />

The old relationship tuple no longer exists.

<CheckRequestViewer user={'user:anne'} relation={'editor'} object={'document:roadmap'} allowed={false} />

##### 04. Remove obsolete relationship from the model

After you remove the previous relationship tuples, update your authorization model to remove the obsolete relation.

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'document',
        relations: {
          writer: {
            this: {},
          },
          can_write: {
            computedUserset: {
              object: '',
              relation: 'writer',
            },
          },
        },
        metadata: {
          relations: {
            writer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'user',
      },
    ],
  }}
/>

Now, the `write` API will only accept the new relation name.

#### Related Sections

<RelatedSection
  description="Review the following sections for more information on managing relationship tuples."
  relatedLinks={[
    {
      title: 'Transactional Writes',
      description: 'Learn how to perform transactional write',
      link: '../../interacting/transactional-writes',
      id: '../../interacting/transactional-writes.mdx',
    },
    {
      title: 'Relationship Queries',
      description: 'Understand the differences between check, read, expand and list objects.',
      link: '../../interacting/relationship-queries',
      id: '../../interacting/relationship-queries.mdx',
    },
    {
      title: 'Production Best Practices',
      description: 'Learn the best practices of running OpenFGA in a production environment',
      link: '../../best-practices/running-in-production',
      id: '../../best-practices/running-in-production',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/migrating/migrating-relations.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/migrating/overview.mdx -->

---
id: overview
title: 'Model Migrations'
slug: /modeling/migrating
sidebar_position: 0
---

import { DocumentationNotice, IntroCard, CardGrid } from '@components/Docs';

<DocumentationNotice />

This section has guides that focus on migrating models and relations.

<IntroCard
  title="When to use"
  description="The content in this section is useful:"
  listItems={[
    `If you want to introduce changes to your existing authorization model or upgrade it to a new schema version.`,
  ]}
/>

### Content

<CardGrid 
  middle={[
    {
      title: 'Migrating Relations',
      description: 'A end-to-end example on renaming a relation.',
      to: 'migrating/migrating-relations',
    },
    {
      title: 'Migrating Models',
      description: 'Learn how to safely update your model.',
      to: 'migrating/migrating-models',
    },
  ]}
/>

<!-- End of openfga/openfga.dev/docs/content/modeling/migrating/overview.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/blocklists.mdx -->

---
sidebar_position: 8
slug: /modeling/blocklists
description: Preventing certain users from accessing objects
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  Playground,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  RelationshipTuplesViewer,
  WriteRequestViewer,
} from '@components/Docs';

### Blocklists

<DocumentationNotice />

In this guide you'll see how to model preventing users from accessing objects using <ProductName format={ProductNameFormat.ProductLink}/>. For example, `blocking` users from accessing a `document`, even if it has been already shared with them.

<CardBox title="When to use" appearance="filled">

Exclusion is useful while building applications. You may need to support access patterns like granting access to some users, but excluding specific people or groups, similar to how users can block others from following them on social media, or prevent them from sharing documents on Google Drive.

This is useful when:

- Implementing the "blocking" feature, such as the profile blocking commonly present on social media platforms (e.g. Instagram and Twitter).
- Reduce a user's access if they are part of a particular group (e.g. restricting access to members who are also guests, or restricting access to users in a certain locality).

</CardBox>

#### Before you start

Before you start this guide, make sure you're familiar with some <ProductConcept /> and know how to develop the things listed below.

<details>
<summary>

You will start with the _<ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />_ below, it represents a `document` _<ProductConcept section="what-is-a-type" linkName="type" />_ that can have users **<ProductConcept section="what-is-a-relation" linkName="related" />** as `editor`, and `team` type that can have users related as `member`.

Let us also assume that we have a `document` called "planning", shared for editing within the product `team` (comprised of becky and carl).

</summary>

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            editor: { directly_related_user_types: [{ type: 'user' }, { type: 'team', relation: 'member' }] },
          },
        },
      },
      {
        type: 'team',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

The current state of the system is represented by the following relationship tuples being in the system already:

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      _description: 'Members of the product team can edit the planning document',
      user: 'team:product#member',
      relation: 'editor',
      object: 'document:planning',
    },
    {
      _description: 'Becky is a member of the product team',
      user: 'user:becky',
      relation: 'member',
      object: 'team:product',
    },
    {
      _description: 'Carl is a member of the product team',
      user: 'user:carl',
      relation: 'member',
      object: 'team:product',
    },
  ]}
/>

<hr />

In addition, you will need to know the following:

##### Modeling user groups

You need to know how to add users to groups and grant groups access to resources. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/.//user-groups.mdx)

##### <ProductName format={ProductNameFormat.ShortForm} /> Concepts

- A <ProductConcept section="what-is-a-type" linkName="Type" />: a class of objects that have similar characteristics
- A <ProductConcept section="what-is-a-user" linkName="User" />: an entity in the system that can be related to an object
- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: is a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system
- An <ProductConcept section="what-is-an-object" linkName="Object" />: represents an entity in the system. Users' relationships to it can be define through relationship tuples and the authorization model
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a grouping consisting of a user, a relation and an object stored in <ProductName format={ProductNameFormat.ShortForm} />
- [Exclusion Operator](https://github.com/openfga/openfga.dev/blob/main/../configuration-language.mdx#the-exclusion-operator): the exclusion operator can be used to exclude certain usersets from being related to an object

</details>

<Playground />

#### Step by step

With the above authorization model and relationship tuples, <ProductName format={ProductNameFormat.LongForm} /> will correctly respond with `{"allowed":true}` when <ProductConcept section="what-is-a-check-request" linkName="check" /> is called to see if Carl and Becky can edit this `document`.

We can verify that by issuing two check requests:

<CheckRequestViewer user={'user:becky'} relation={'editor'} object={'document:planning'} allowed={true} />

<CheckRequestViewer user={'user:carl'} relation={'editor'} object={'document:planning'} allowed={true} />

We want to share a document with the product team and also have the ability to deny certain users access, even if they have the document shared with them already. We can verify this by blocking Carl (who we have seen already has edit access) from editing the document.

In order to do that, we need to:

1. [Modify our model to allow indicating that users can be blocked from accessing a document](#01-modify-our-model-so-users-can-be-blocked-from-accessing-a-document)
2. [Modify our model to indicate that users who are blocked can no longer edit the document](#02-modify-our-model-so-users-who-are-blocked-can-no-longer-edit-the-document)
3. [Verify that our solution works](#03-verify-our-solution-works):

a. [Indicate that Carl is blocked from the planning document](#a-indicate-that-carl-is-blocked-from-the-planning-document)

b. [Carl (now blocked) can no longer edit the document](#b-carl-now-blocked-can-no-longer-edit-the-document)

c. [Becky still has edit access](#c-becky-still-has-edit-access)

##### 01. Modify our model so users can be blocked from accessing a document

To allow users to be "blocked" from accessing a `document`, we first need to allow this relation. We'll update our store model to add a `blocked` <ProductConcept section="what-is-a-relation" linkName="relation" /> to the `document` type.

The authorization model becomes this:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          blocked: {
            this: {},
          },
          editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            blocked: { directly_related_user_types: [{ type: 'user' }] },
            editor: { directly_related_user_types: [{ type: 'user' }, { type: 'team', relation: 'member' }] },
          },
        },
      },
      {
        type: 'team',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

Now we can add relationship tuples indicating that a certain user is `blocked` from editing a `document`.

##### 02. Modify our model so users who are blocked can no longer edit the document

Now that we can mark users as `blocked` from editing documents, we need to support denying the `editor` relationship when a user is `blocked`. We do that by modifying the relation definition of `editor`, and making use of the [**exclusion operator**](https://github.com/openfga/openfga.dev/blob/main/../configuration-language.mdx#the-exclusion-operator) to exclude the set of `blocked` users, as we can see here:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          blocked: {
            this: {},
          },
          editor: {
            difference: {
              base: {
                this: {},
              },
              subtract: {
                computedUserset: {
                  relation: 'blocked',
                },
              },
            },
          },
        },
        metadata: {
          relations: {
            blocked: { directly_related_user_types: [{ type: 'user' }] },
            editor: { directly_related_user_types: [{ type: 'user' }, { type: 'team', relation: 'member' }] },
          },
        },
      },
      {
        type: 'team',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

##### 03. Verify our solution works

To check if our new model works, we'll add a relationship tuple with Carl as `blocked` from `document:planning` and then verify that Carl no longer has `editor` access to that document.

###### a. Indicate that Carl is blocked from the planning document

With our modified authorization model, we can indicate that Carl is blocked by adding this _<ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuple" />_.

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'Carl is blocked from editing the planning document',
      user: 'user:carl',
      relation: 'blocked',
      object: 'document:planning',
    },
  ]}
/>

###### b. Carl (now blocked) can no longer edit the document

We have modified the authorization model and added relationship tuples to indicate that Carl is `blocked`. Now let's make sure our solution works as expected.

To check if Carl still has access to the document, we can issue a check request with Carl as the user.

<CheckRequestViewer user={'user:carl'} relation={'editor'} object={'document:planning'} allowed={false} />

The response is `false`, so our solution is working as expected.

###### c. Becky still has edit access

To check if Becky still has access to the document, we'll issue another check request with Becky as the user.

<CheckRequestViewer user={'user:becky'} relation={'editor'} object={'document:planning'} allowed={true} />

The response is `true`, indicating our model change did not inadvertently deny access for users who have access but are not blocked.

:::caution
When creating tuples for <ProductName format={ProductNameFormat.LongForm} /> make sure to use unique ids for each object and user within your application domain. We are using first names and human-readable identifiers to make this task easier to read.
:::

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to model with {ProductName}."
  relatedLinks={[
    {
      title: 'Modeling: Getting Started',
      description: 'Learn about how to get started with modeling.',
      link: './getting-started',
      id: './getting-started',
    },
    {
      title: 'Configuration Language',
      description: 'Learn about {ProductName} Configuration Language.',
      link: '../configuration-language',
      id: '../configuration-language',
    },
    {
      title: 'Public Access',
      description: 'Learn about model public access.',
      link: './public-access',
      id: './public-access',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/blocklists.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/conditions.mdx -->

---
sidebar_position: 8
slug: /modeling/conditions
description: Modeling relationships with Conditions
---

import {
  AuthzModelSnippetViewer,
  WriteRequestViewer,
  CheckRequestViewer,
  ListObjectsRequestViewer,
  languageLabelMap,
  SdkSetupPrerequisite,
  SupportedLanguage,
  WriteAuthzModelViewer,
} from '@components/Docs';

import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

### Conditions

#### Overview
Conditions allow you to model more complex authorization modeling scenarios involving attributes and can be used to represent some Attribute-based Access Control (ABAC) policies. Take a look at the [Conditions](https://github.com/openfga/openfga.dev/blob/main/../concepts.mdx#what-is-a-condition) and [Conditional Relationship Tuples](https://github.com/openfga/openfga.dev/blob/main/../concepts.mdx#what-is-a-conditional-relationship-tuple) concepts for a quick overview.

There are various use cases where Conditions can be helpful. These include, but are not limited to:

* [**Temporal Access Policies**](https://github.com/openfga/sample-stores/tree/main/stores/temporal-access) - manage user access for a window of time.
* [**IP Allowlists or Geo-fencing Policies**](https://github.com/openfga/sample-stores/tree/main/stores/ip-based-access) - limit or grant access based on an IP Address range or corporate network policy.
* [**Usage-based/Feature-based Policies (Entitlements)**](https://github.com/openfga/sample-stores/tree/main/stores/advanced-entitlements) - enforce quota or usage of some resource or feature.
* [**Resource-attribute Policies**](https://github.com/openfga/sample-stores/tree/main/stores/groups-resource-attributes) - define policies to access resources based on attributes/fields of the resource(s).

For more information and background context on why we added this feature, please see our blog post on [Conditional Relationship Tuples for OpenFGA](https://openfga.dev/blog/conditional-tuples-announcement). 

#### Defining conditions in models
For this example we'll use the following authorization model to demonstrate a temporal based access policy. Namely, a user can view a document if and only if they have been granted the viewer relationship AND their non-expired grant policy is met.

```dsl.openfga
model
  schema 1.1

type user

type document
  relations
    define viewer: [user with non_expired_grant]

condition non_expired_grant(current_time: timestamp, grant_time: timestamp, grant_duration: duration) {
  current_time < grant_time + grant_duration
}
```

:::note
The type restriction for `document#viewer` requires that any user of type `user` that is written in the relationship tuple must be accompanied by the `non_expired_grant` condition. This is denoted by the `user with non_expired_grant` specification.
:::

Write the model to the FGA store:
<WriteAuthzModelViewer
  authorizationModel={{
    "schema_version":"1.1",
    "type_definitions": [
      {
        "type":"user"
      },
      {
        "type":"document",
        "relations": {
          "viewer": {
            "this": {}
          }
        },
        "metadata": {
          "relations": {
            "viewer": {
              "directly_related_user_types": [
                {
                    "type":"user",
                    "condition":"non_expired_grant"
                }
              ]
            }
          }
        }
      }
    ],
    "conditions": {
      "non_expired_grant": {
        "name":"non_expired_grant",
        "expression":"current_time < grant_time + grant_duration",
        "parameters": {
          "current_time": {
              "type_name":"TYPE_NAME_TIMESTAMP"
          },
          "grant_duration": {
              "type_name":"TYPE_NAME_DURATION"
          },
          "grant_time": {
              "type_name":"TYPE_NAME_TIMESTAMP"
          }
        }
      }
    }
  }}
  skipSetup={true}
  allowedLanguages={[
    SupportedLanguage.JS_SDK,
    SupportedLanguage.GO_SDK,
    SupportedLanguage.DOTNET_SDK,
    SupportedLanguage.PYTHON_SDK,
    SupportedLanguage.JAVA_SDK,
    SupportedLanguage.CLI,
    SupportedLanguage.CURL,
  ]}
/>

#### Writing conditional relationship tuples
Using the model above, when we [Write](https://openfga.dev/api/service#/Relationship%20Tuples/Write) relationship tuples to the OpenFGA store, then any `document#viewer` relationship with `user` objects must be accompanied by the condition `non_expired_grant` because the type restriction requires it.

For example, we can give `user:anne` viewer access to `document:1` for 10 minutes by writing the following relationship tuple:

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:anne',
      relation: 'viewer',
      object: 'document:1',
      condition: {
        name: 'non_expired_grant',
        context: {
          grant_time: '2023-01-01T00:00:00Z',
          grant_duration: '10m',
        }
      }
    },
  ]}
  skipSetup={true}
  allowedLanguages={[
    SupportedLanguage.JS_SDK,
    SupportedLanguage.GO_SDK,
    SupportedLanguage.DOTNET_SDK,
    SupportedLanguage.PYTHON_SDK,
    SupportedLanguage.JAVA_SDK,
    SupportedLanguage.CLI,
    SupportedLanguage.CURL,
  ]}
/>

#### Queries with condition context
Now that we have written a [Conditional Relationship Tuple](https://github.com/openfga/openfga.dev/blob/main/../concepts.mdx#what-is-a-conditional-relationship-tuple), we can query OpenFGA using the [Check API](https://openfga.dev/api/service#/Relationship%20Queries/Check) to see if `user:anne` has viewer access to `document:1` under certain conditions/context. That is, `user:anne` should only have access if the current timestamp is less than the grant timestamp (e.g. the time which the tuple was written) plus the duration of the grant (10 minutes). If the current timestamp is less than, then you'll get a permissive decision. For example,

<CheckRequestViewer
  user={'user:anne'}
  relation={'viewer'}
  object={'document:1'}
  context={{current_time: "2023-01-01T00:09:50Z"}}
  allowed={true}
  skipSetup={true}
  allowedLanguages={[
    SupportedLanguage.JS_SDK,
    SupportedLanguage.GO_SDK,
    SupportedLanguage.DOTNET_SDK,
    SupportedLanguage.PYTHON_SDK,
    SupportedLanguage.JAVA_SDK,
    SupportedLanguage.CLI,
    SupportedLanguage.CURL,
  ]}
/>

but if the current time is outside the grant window then you get a deny decision. For example,

<CheckRequestViewer
  user={'user:anne'}
  relation={'viewer'}
  object={'document:1'}
  context={{current_time: "2023-01-01T00:10:01Z"}}
  allowed={false}
  skipSetup={true}
  allowedLanguages={[
    SupportedLanguage.JS_SDK,
    SupportedLanguage.GO_SDK,
    SupportedLanguage.DOTNET_SDK,
    SupportedLanguage.PYTHON_SDK,
    SupportedLanguage.JAVA_SDK,
    SupportedLanguage.CLI,
    SupportedLanguage.CURL,
  ]}
/>

Similarly, we can use the [ListObjects API](https://openfga.dev/api/service#/Relationship%20Queries/ListObjects) to return all of the documents that `user:anne` has viewer access given the current time. For example,

<ListObjectsRequestViewer
  objectType="document"
  relation="viewer"
  user="user:anne"
  context={{current_time: "2023-01-01T00:09:50Z"}}
  expectedResults={['document:1']}
  skipSetup={true}
  allowedLanguages={[
    SupportedLanguage.JS_SDK,
    SupportedLanguage.GO_SDK,
    SupportedLanguage.DOTNET_SDK,
    SupportedLanguage.PYTHON_SDK,
    SupportedLanguage.JAVA_SDK,
    SupportedLanguage.CLI,
    SupportedLanguage.CURL,
  ]}
/>

but if the current time is outside the grant window then we don't get the object in the response. For example,

<ListObjectsRequestViewer
  objectType="document"
  relation="viewer"
  user="user:anne"
  context={{current_time: "2023-01-01T00:10:01Z"}}
  expectedResults={[]}
  skipSetup={true}
  allowedLanguages={[
    SupportedLanguage.JS_SDK,
    SupportedLanguage.GO_SDK,
    SupportedLanguage.DOTNET_SDK,
    SupportedLanguage.PYTHON_SDK,
    SupportedLanguage.JAVA_SDK,
    SupportedLanguage.CLI,
    SupportedLanguage.CURL,
  ]}
/>

:::note
When evaluating a condition at request time, the context written/persisted in the relationship tuple and the context provided at request time are merged together into a single evaluation context.

If you provide a context value in the request context that is also written/persisted in the relationship tuple, then the context values written in the relationship tuple take precedence. That is, the merge strategy is such that persisted context has higher precedence than request context.
:::

#### Examples
For more examples take a look at our [Sample Stores](https://github.com/openfga/sample-stores) repository. There are various examples with ABAC models in that repository.


#### Supported parameter types
The following table enumerates the list of supported parameter types. The more formal list is defined in https://github.com/openfga/openfga/tree/main/internal/condition/types.

Note that some of the types support generics, these types are indicated with `<T>`.

| Friendly Type Name | Type Name (Protobuf Enum) | Description                                                                                                                                                                                                                                                       | Examples                                                                                |
|--------------------|---------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------|
| int                | TYPE_NAME_INT             | A 64-bit signed integer value.                                                                                                                                                                                                                                    | -1<br />"-1"                                                                              |
| uint               | TYPE_NAME_UINT            | A 64-bit unsigned integer value.                                                                                                                                                                                                                                  | 1<br />"1"                                                                                |
| double             | TYPE_NAME_DOUBLE          | A double-width floating point value, represented equivalently as a Go `float64` value.<br /><br />If the value is provided as a string we parse it with `strconv.ParseFloat(s, 64)`. See [`strconv.ParseFloat`](https://pkg.go.dev/strconv#ParseFloat) for more info. | 3.14159<br />-0.75<br />"1"<br />"-2.5"                                                       |
| bool               | TYPE_NAME_BOOL            | A boolean value.                                                                                                                                                                                                                                                  | true<br />false<br /><br />"true"<br />"false"                                                  |
| bytes              | TYPE_NAME_BYTES           | An array of byte values specified as a byte string.                                                                                                                                                                                                               | "bytestring"                                                                            |
| string             | TYPE_NAME_STRING          | A string value.                                                                                                                                                                                                                                                   | "hello, world"                                                                          |
| duration           | TYPE_NAME_DURATION        | A value representing a duration of time specified using Go duration string format.<br /><br />See [time.Duration#ParseDuration](https://pkg.go.dev/time#ParseDuration)                                                                                                | "120s"<br />"2m"                                                                          |
| timestamp          | TYPE_NAME_TIMESTAMP       | A timestamp value that follows the RFC3339 specification.                                                                                                                                                                                                         | "2023-01-01T00:00:00Z"                                                                    |
| any                | TYPE_NAME_ANY             | A variant type which permits any value to be provided.                                                                                                                                                                                                            | \{"x": 1\}<br />"hello"<br />["a", "b"]                                                       |
| list\<T\>            | TYPE_NAME_LIST            | A list of values of generic type T.                                                                                                                                                                                                                               | list\<string\> - ["a", "b", "c"]<br />list\<int\> - [-1, 1]<br />list\<duration\> - ["60s", "1m"] |
| map\<T\>             | TYPE_NAME_MAP             | A map whose keys are strings and whose values are values of generic type T.<br /><br />Any map value must have string keys, only the value types can vary.                                                                                                            | map\<int\> - \{"x": -1, "y": 1\}<br />map\<string\> - \{"key": "value"\}                          |
| ipaddress          | TYPE_NAME_IPADDRESS       | A custom value type specified as a string representation of an IP Address.                                                                                                                                                                                        | "192.168.0.1"                                                                           |


#### Limitations
* The size of the condition `context` parameter that can be written alongside a relationship tuple is limited to 32KB in size.

* The size of the condition `context` parameter for query requests (e.g. Check, ListObjects, etc.) is not explicitly limited, but the OpenFGA server has an overall request size limit of 512KB at this time.

* We enforce a maximum Google CEL expression evaluation cost of 100 (by default) to protect the server from malicious conditions. The evaluation cost of a CEL expression is a function of the size the input that is being compared and the composition of the expression. For more general information please see the official [Language Definition for Google CEL](https://github.com/google/cel-spec/blob/master/doc/langdef.md). If you hit these limits with practical use-cases, please reach out to the maintainer team and we can discuss.


<!-- End of openfga/openfga.dev/docs/content/modeling/conditions.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/contextual-time-based-authorization.mdx -->

---
sidebar_position: 8
slug: /modeling/contextual-time-based-authorization
description: Checking relations that depends on certain dynamic or contextual data (such as time, location, ip address, weather) that have not been written
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  Playground,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  RelationshipTuplesViewer,
  UpdateProductNameInLinks,
  WriteRequestViewer,
} from '@components/Docs';

### Contextual and Time-Based Authorization

<DocumentationNotice />

This section explores some methods available to you to tackle some use-cases where the expected authorization check may depend on certain dynamic or contextual data (such as time, location, ip address, weather) that have not been written to the <ProductName format={ProductNameFormat.ShortForm}/> store.

<CardBox title="When to use" appearance="filled">

Contextual Tuples should be used when modeling cases where a user's access to an object depends on the context of their request. For example:

- An employee’s ability to access a document when they are connected to the company VPN or the api call is originating from an internal IP address.
- A support engineer is only able to access a user's account during office hours.
- If a user belongs to multiple organizations, they are only able to access a resource if they set a specific organization in their current context.

</CardBox>

#### Before you start

To follow this guide, you should be familiar with some <ProductConcept />.

##### <ProductName format={ProductNameFormat.ShortForm}/> concepts

- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: Defined in the type definition of an authorization model, a relation is a string that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system.
- A <ProductConcept section="what-is-a-check-request" linkName="Check Request" />: is a call to the <ProductName format={ProductNameFormat.ShortForm}/> check endpoint that returns whether the user has a certain relationship with an object.
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a grouping consisting of a user, a relation and an object stored in <ProductName format={ProductNameFormat.ShortForm}/>
- A <ProductConcept section="what-are-contextual-tuples" linkName="Contextual Tuple" />: a tuple that can be added to a Check request, and only exists within the context of that particular request.

You also need to be familiar with:

- **Modeling Object-to-Object Relationships**: You need to know how to create relationships between objects and how that might affect a user's relationships to those objects. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/./building-blocks/object-to-object-relationships.mdx)
- **Modeling Multiple Restrictions**: You need to know how to model requiring multiple authorizations before allowing users to perform certain actions. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/./multiple-restrictions.mdx)

<Playground />

##### Scenario

For the scope of this guide, we are going to consider the following scenario.

Consider you are building the authorization model for WeBank Inc.

In order for an Account Manager at WeBank Inc. to be able to access a customer's account and its transactions, they would need to be:

- An account manager at the same branch as the customer's account
- Connected via the branch's internal network or through the branch's VPN
- Connected during this particular branch's office hours

We will start with the following Authorization Model

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'branch',
        relations: {
          account_manager: {
            this: {},
          },
        },
        metadata: {
          relations: {
            account_manager: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'account',
        relations: {
          branch: {
            this: {},
          },
          account_manager: {
            tupleToUserset: {
              tupleset: {
                object: '',
                relation: 'branch',
              },
              computedUserset: {
                object: '',
                relation: 'account_manager',
              },
            },
          },
          customer: {
            this: {},
          },
          viewer: {
            union: {
              child: [
                {
                  computedUserset: {
                    object: '',
                    relation: 'customer',
                  },
                },
                {
                  computedUserset: {
                    object: '',
                    relation: 'account_manager',
                  },
                },
              ],
            },
          },
          can_view: {
            computedUserset: {
              object: '',
              relation: 'viewer',
            },
          },
        },
        metadata: {
          relations: {
            branch: { directly_related_user_types: [{ type: 'branch' }] },
            customer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'transaction',
        relations: {
          account: {
            this: {},
          },
          can_view: {
            tupleToUserset: {
              tupleset: {
                object: '',
                relation: 'account',
              },
              computedUserset: {
                object: '',
                relation: 'viewer',
              },
            },
          },
        },
        metadata: {
          relations: {
            account: { directly_related_user_types: [{ type: 'account' }] },
          },
        },
      },
    ],
  }}
/>

<details>
<summary>
We are considering the case that:

- Anne is the Account Manager at the West-Side branch
- Caroline is the customer for checking account number 526
- The West-Side branch is the branch that the checking account number 526 has been created at
- Checking account number 526 has a transaction, we'll call it transaction A
- The West-Side branch’s office hours is from 8am-3pm UTC

</summary>

The above state translates to the following relationship tuples:

<WriteRequestViewer relationshipTuples={[
  {
    "_description": "Anne is the Account Manager at the West-Side branch",
    "user": "user:anne",
    "relation": "account_manager",
    "object": "branch:west-side"
  },
  {
    "_description": "Caroline is the customer for checking account number 526",
    "user": "user:caroline",
    "relation": "customer",
    "object": "account:checking-526"
  },
  {
    "_description": "The West-Side branch is the branch that the Checking account number 526 has been created at",
    "user": "branch:west-side",
    "relation": "branch",
    "object": "account:checking-526"
  },
  {
    "_description": "Checking account number 526 is the account for transaction A",
    "user": "account:checking-526",
    "relation": "account",
    "object": "transaction:A"
  },
]} />
</details>

##### Requirements

By the end of this guide we would like to validate that:

- If Anne is at the branch, and it is 12pm UTC, Anne should be able to view transaction A
- If Anne is connecting remotely at 12pm UTC but is not connected to the VPN, Anne should not be able to view transaction A
- If Anne is connecting remotely and is connected to the VPN
  - at 12pm UTC, should be able to view transaction A
  - at 6pm UTC, should not be able to view transaction A

#### Step by step

In order to solve for the requirements above, we will break the problem down to three steps:

1. [Understand relationships without contextual tuples](#understand-relationships-without-contextual-data). We will want to ensure that

- the customer can view a transaction tied to their account
- the account manager can view a transaction whose account is at the same branch

2. Extend the Authorization Model to [take time and ip address into consideration](#take-time-and-ip-address-into-consideration)
3. [Use contextual tuples for context related checks](#use-contextual-tuples-for-context-related-checks).

##### Understand relationships without contextual data

With the Authorization Model and relationship tuples shown above, <ProductName format={ProductNameFormat.ShortForm}/> has all the information needed to

- Ensure that the customer can view a transaction tied to their account
- Ensure that the account manager can view a transaction whose account is at the same branch

We can verify that using the following checks

Anne can view transaction:A because she is an account manager of an account that is at the same branch.

<CheckRequestViewer user={'user:anne'} relation={'can_view'} object={'transaction:A'} allowed={true} />

Caroline can view transaction:A because she is a customer and the transaction is tied to her account.

<CheckRequestViewer user={'user:caroline'} relation={'can_view'} object={'transaction:A'} allowed={true} />

Additionally, we will check that Mary, an account manager at a different branch _cannot_ view transaction A.

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'Mary is an account manager at the East-Side branch',
      user: 'user:mary',
      relation: 'account_manager',
      object: 'branch:east-side',
    },
  ]}
/>
<CheckRequestViewer user={'user:mary'} relation={'can_view'} object={'transaction:A'} allowed={false} />

Note that so far, we have not prevented Anne from viewing the transaction outside office hours, let's see if we can do better.

##### Take time and IP address into consideration

###### Extend the authorization model

In order to add time and ip address to our authorization model, we will add appropriate types for them. We will have a "timeslot" and an "ip-address-range" as types, and each can have users related to it as a user.

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type: 'timeslot',
    relations: {
      user: {
        this: {},
      },
    },
    metadata: {
      relations: {
        user: { directly_related_user_types: [{ type: 'user' }] },
      },
    },
  }} skipVersion={true}
/>

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type: 'ip-address-range',
    relations: {
      user: {
        this: {},
      },
    },
    metadata: {
      relations: {
        user: { directly_related_user_types: [{ type: 'user' }] },
      },
    },
  }} skipVersion={true}
/>

We'll also need to introduce some new relations, and modify some others.

1. On the "branch" type:

- Add "approved_timeslot" relation to mark than a certain timeslot is approved to view transactions for accounts in this branch
- Add "approved_ip_address_range" relation to mark than an ip address range is approved to view transactions for accounts in this branch
- Add "approved_context" relation to combine the two authorizations above (`user from approved_timeslot and user from approved_ip_address_range`), and indicate that the user is in an approved context

The branch type definition then becomes:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type: 'branch',
    relations: {
      account_manager: {
        this: {},
      },
      approved_ip_address_range: {
        this: {},
      },
      approved_timeslot: {
        this: {},
      },
      approved_context: {
        intersection: {
          child: [
            {
              tupleToUserset: {
                tupleset: {
                  object: '',
                  relation: 'approved_timeslot',
                },
                computedUserset: {
                  object: '',
                  relation: 'user',
                },
              },
            },
            {
              tupleToUserset: {
                tupleset: {
                  object: '',
                  relation: 'approved_ip_address_range',
                },
                computedUserset: {
                  object: '',
                  relation: 'user',
                },
              },
            },
          ],
        },
      },
    },
    metadata: {
      relations: {
        account_manager: { directly_related_user_types: [{ type: 'user' }] },
        approved_ip_address_range: { directly_related_user_types: [{ type: 'ip-address-range' }] },
        approved_timeslot: { directly_related_user_types: [{ type: 'timeslot' }] },
      },
    },
  }} skipVersion={true}
/>

2. On the "account" type:

- Add "account_manager_viewer" relation to combine the "account_manager" relationship and the new "approved_context" relation from the branch
- Update the "viewer" relation definition to `customer or account_manager_viewer` where "customer" can view without being subjected to contextual authorization, while "account_manager_viewer" needs to be within the branch allowed context to view

The account type definition then becomes:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type: 'account',
    relations: {
      branch: {
        this: {},
      },
      account_manager: {
        tupleToUserset: {
          tupleset: {
            object: '',
            relation: 'branch',
          },
          computedUserset: {
            object: '',
            relation: 'account_manager',
          },
        },
      },
      customer: {
        this: {},
      },
      account_manager_viewer: {
        intersection: {
          child: [
            {
              computedUserset: {
                object: '',
                relation: 'account_manager',
              },
            },
            {
              tupleToUserset: {
                tupleset: {
                  object: '',
                  relation: 'branch',
                },
                computedUserset: {
                  object: '',
                  relation: 'approved_context',
                },
              },
            },
          ],
        },
      },
      viewer: {
        union: {
          child: [
            {
              computedUserset: {
                object: '',
                relation: 'customer',
              },
            },
            {
              computedUserset: {
                object: '',
                relation: 'account_manager_viewer',
              },
            },
          ],
        },
      },
      can_view: {
        computedUserset: {
          object: '',
          relation: 'viewer',
        },
      },
    },
    metadata: {
      relations: {
        branch: { directly_related_user_types: [{ type: 'branch' }] },
        customer: { directly_related_user_types: [{ type: 'user' }] },
      },
    },
  }} skipVersion={true}
/>

:::note
On the "transaction" type:

- Nothing will need to be done, as it will inherit the updated "viewer" relation definition from "account"

:::

###### Add the required tuples to mark that Anne is in an approved context

Now that we have updated our authorization model to take time and ip address into consideration, you will notice that Anne has lost access because nothing indicates that Anne is connecting from an approved ip address and time. You can verify that by issuing the following check:

<CheckRequestViewer user={'user:anne'} relation={'can_view'} object={'transaction:A'} allowed={false} />

We need to add relationship tuples to mark some approved timeslots and ip address ranges:

:::note

- Here we added the time slots in increments of 1 hour periods, but this is not a requirement.
- We did not add all the office hours to keep this guide shorter.

:::

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: '11am to 12pm is within the office hours of the West-Side branch',
      user: 'timeslot:11_12',
      relation: 'approved_timeslot',
      object: 'branch:west-side',
    },
    {
      _description: '12pm to 1pm is within the office hours of the West-Side branch',
      user: 'timeslot:12_13',
      relation: 'approved_timeslot',
      object: 'branch:west-side',
    },
    {
      _description: 'The office VPN w/ the 10.0.0.0/16 address range is approved for the West-Side branch',
      user: 'ip-address-range:10.0.0.0/16',
      relation: 'approved_ip_address_range',
      object: 'branch:west-side',
    },
  ]}
/>

Now that we have added the allowed timeslots and ip address ranges we need to add the following relationship tuples to give Anne access.

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'Anne is connecting from within the 10.0.0.0/16 ip address range',
      user: 'user:anne',
      relation: 'user',
      object: 'ip-address-range:10.0.0.0/16',
    },
    {
      _description: 'Anne is connecting between 12pm and 1pm',
      user: 'user:anne',
      relation: 'user',
      object: 'timeslot:12_13',
    },
  ]}
/>

If we have the above two tuples in the system, when checking whether Anne can view transaction A we should get a response stating that Anne can view it.

<CheckRequestViewer user={'user:anne'} relation={'can_view'} object={'transaction:A'} allowed={true} />

##### Use contextual tuples for context related checks

Now that we know we can authorize based on present state, we have a different problem to solve. We are storing the tuples in the state in order for <ProductName format={ProductNameFormat.ShortForm}/> to evaluate them, which means that:

- For the case of the IP Address, we are not able to truly authorize based on the context of the request. E.g. if Anne was trying to connect from the phone and from the PC at the same time, and only the PC was connected to the VPN, how would <ProductName format={ProductNameFormat.ShortForm}/> know to deny one and allow the other if the data is stored in the state?
- On every check call we have to first write the correct tuples, then call the Check api, then clean up those tuples. This causes a substantial increase in latency as well as incorrect answers for requests happening in parallel (they could write/delete each other's tuples).

How do we solve this? How do we tie the above two tuples to the context of the request instead of the system state?

First, we will need to undo adding the stored relationship tuples where Anne is connecting from within the 10.0.0.0/16 ip address range and Anne connecting between 12pm and 1pm

<WriteRequestViewer
  deleteRelationshipTuples={[
    {
      _description: 'Remove stored tuples where Anne is connecting from within the 10.0.0.0/16 ip address range',
      user: 'user:anne',
      relation: 'user',
      object: 'ip-address-range:10.0.0.0/16',
    },
    {
      _description: 'Remove stored tuples where Anne is connecting between 12pm and 1pm',
      user: 'user:anne',
      relation: 'user',
      object: 'timeslot:12_13',
    },
  ]}
/>

For Check calls, <ProductName format={ProductNameFormat.ShortForm}/> has a concept called "<ProductConcept section="what-are-contextual-tuples" linkName="Contextual Tuples" />". Contextual Tuples are tuples that do **not** exist in the system state and are not written beforehand to <ProductName format={ProductNameFormat.ShortForm}/>. They are tuples that are sent alongside the Check request and will be treated as _if_ they already exist in the state for the context of that particular Check call.

When Anne is connecting from an allowed ip address range and timeslot, <ProductName format={ProductNameFormat.ShortForm}/> will return `{"allowed":true}`:

<CheckRequestViewer
  user={'user:anne'}
  relation={'can_view'}
  object={'transaction:A'}
  allowed={true}
  contextualTuples={[
    {
      _description: 'Anne is connecting from within the 10.0.0.0/16 ip address range',
      user: 'user:anne',
      relation: 'user',
      object: 'ip-address-range:10.0.0.0/16',
    },
    {
      _description: 'Anne is connecting between 12pm and 1pm',
      user: 'user:anne',
      relation: 'user',
      object: 'timeslot:12_13',
    },
  ]}
/>

When Anne is connecting from a denied ip address range or timeslot, <ProductName format={ProductNameFormat.ShortForm}/> will return `{"allowed":false}`:

<CheckRequestViewer
  user={'user:anne'}
  relation={'can_view'}
  object={'transaction:A'}
  allowed={false}
  contextualTuples={[
    {
      _description: 'Anne is connecting from within the 10.0.0.0/16 ip address range',
      user: 'user:anne',
      relation: 'user',
      object: 'ip-address-range:10.0.0.0/16',
    },
    {
      _description: 'Anne is connecting between 6pm and 7pm',
      user: 'user:anne',
      relation: 'user',
      object: 'timeslot:18_19',
    },
  ]}
/>

#### Summary

<details>
<summary>
  Final version of the Authorization Model and Relationship tuples
</summary>
<AuthzModelSnippetViewer configuration={{
    schema_version: '1.1',
  "type_definitions": [
    {
      "type": "user",
    },
    {
      "type": "branch",
      "relations": {
        "account_manager": {
          "this": {}
        },
        "approved_ip_address_range": {
          "this": {}
        },
        "approved_timeslot": {
          "this": {}
        },
        "approved_context": {
          "intersection": {
            "child": [
              {
                "tupleToUserset": {
                  "tupleset": {
                    "object": "",
                    "relation": "approved_timeslot"
                  },
                  "computedUserset": {
                    "object": "",
                    "relation": "user"
                  }
                }
              },
              {
                "tupleToUserset": {
                  "tupleset": {
                    "object": "",
                    "relation": "approved_ip_address_range"
                  },
                  "computedUserset": {
                    "object": "",
                    "relation": "user"
                  }
                }
              }
            ]
          }
        }
      },
      metadata: {
        relations: {
          account_manager: { directly_related_user_types: [{type: 'user'}] },
          approved_ip_address_range: { directly_related_user_types: [{type: 'ip-address-range'}] },
          approved_timeslot: { directly_related_user_types: [{type: 'timeslot'}] },
        },
      },
    },
    {
      "type": "account",
      "relations": {
        "branch": {
          "this": {}
        },
        "account_manager": {
          "tupleToUserset": {
            "tupleset": {
              "object": "",
              "relation": "branch"
            },
            "computedUserset": {
              "object": "",
              "relation": "account_manager"
            }
          }
        },
        "customer": {
          "this": {}
        },
        "account_manager_viewer": {
          "intersection": {
            "child": [
              {
                "computedUserset": {
                  "object": "",
                  "relation": "account_manager"
                }
              },
              {
                "tupleToUserset": {
                  "tupleset": {
                    "object": "",
                    "relation": "branch"
                  },
                  "computedUserset": {
                    "object": "",
                    "relation": "approved_context"
                  }
                }
              }
            ]
          }
        },
        "viewer": {
          "union": {
            "child": [
              {
                "computedUserset": {
                  "object": "",
                  "relation": "customer"
                }
              },
              {
                "computedUserset": {
                  "object": "",
                  "relation": "account_manager_viewer"
                }
              }
            ]
          }
        },
        "can_view": {
          "computedUserset": {
            "object": "",
            "relation": "viewer"
          }
        }
      },
      metadata: {
        relations: {
          branch: { directly_related_user_types: [{type: 'branch'}] },
          customer: { directly_related_user_types: [{type: 'user'}] },
        },
      },
    },
    {
      "type": "transaction",
      "relations": {
        "account": {
          "this": {}
        },
        "can_view": {
          "tupleToUserset": {
            "tupleset": {
              "object": "",
              "relation": "account"
            },
            "computedUserset": {
              "object": "",
              "relation": "viewer"
            }
          }
        }
      },
      metadata: {
        relations: {
          account: { directly_related_user_types: [{type: 'account'}] },
        },
      },
    },
    {
      "type": "timeslot",
      "relations": {
        "user": {
          "this": {}
        }
      },
      metadata: {
        relations: {
          user: { directly_related_user_types: [{type: 'user'}] },
        },
      },
    },
    {
      "type": "ip-address-range",
      "relations": {
        "user": {
          "this": {}
        }
      },
      metadata: {
        relations: {
          user: { directly_related_user_types: [{type: 'user'}] },
        },
      },
    }
  ]
}} />

<WriteRequestViewer relationshipTuples={[
  {
    "_description": "Anne is the Account Manager at the West-Side branch",
    "user": "user:anne",
    "relation": "account_manager",
    "object": "branch:west-side"
  },
  {
    "_description": "Caroline is the customer for checking account number 526",
    "user": "user:caroline",
    "relation": "customer",
    "object": "account:checking-526"
  },
  {
    "_description": "The West-Side branch is the branch that the Checking account number 526 has been created at",
    "user": "branch:west-side",
    "relation": "branch",
    "object": "account:checking-526"
  },
  {
    "_description": "Checking account number 526 is the account for transaction A",
    "user": "account:checking-526",
    "relation": "account",
    "object": "transaction:A"
  },
  {
    "_description": "8am to 9am is within the office hours of the West-Side branch",
    "user": "timeslot:8_9",
    "relation": "approved_timeslot",
    "object": "branch:west-side"
  },
  {
    "_description": "9am to 10am is within the office hours of the West-Side branch",
    "user": "timeslot:9_10",
    "relation": "approved_timeslot",
    "object": "branch:west-side"
  },
  {
    "_description": "10am to 11am is within the office hours of the West-Side branch",
    "user": "timeslot:10_11",
    "relation": "approved_timeslot",
    "object": "branch:west-side"
  },
  {
    "_description": "11am to 12pm is within the office hours of the West-Side branch",
    "user": "timeslot:11_12",
    "relation": "approved_timeslot",
    "object": "branch:west-side"
  },
  {
    "_description": "12pm to 1pm is within the office hours of the West-Side branch",
    "user": "timeslot:12_13",
    "relation": "approved_timeslot",
    "object": "branch:west-side"
  },
  {
    "_description": "1pm to 2pm is within the office hours of the West-Side branch",
    "user": "timeslot:13_14",
    "relation": "approved_timeslot",
    "object": "branch:west-side"
  },
  {
    "_description": "2pm to 3pm is within the office hours of the West-Side branch",
    "user": "timeslot:14_15",
    "relation": "approved_timeslot",
    "object": "branch:west-side"
  },
  {
    "_description": "The office VPN w/ the 10.0.0.0/16 address range is approved for the West-Side branch",
    "user": "ip-address-range:10.0.0.0/16",
    "relation": "approved_ip_address_range",
    "object": "branch:west-side"
  },
]} />
</details>

:::caution Warning
Contextual tuples:

- Are not persisted in the store.
- Are only supported on the <UpdateProductNameInLinks link="/api/service#Relationship%20Queries/Check" name="Check API endpoint" /> and <UpdateProductNameInLinks link="/api/service#Relationship%20Queries/ListObjects" name="ListObjects API endpoint" />. They are not supported on read, expand and other endpoints.
- If you are using the <UpdateProductNameInLinks link="/api/service#Relationship%20Tuples/ReadChanges" name="Read Changes API endpoint" /> to build a permission aware search index, note that it will not be trivial to take contextual tuples into account.

:::

##### Taking it a step further: Banks as a service authorization

In order to keep this guide concise, we assumed you were modeling for a single bank. What if you were offering a multi-tenant service where each bank is a single tenant?

In that case, we can extend the model like so:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'bank',
        relations: {
          admin: {
            this: {},
          },
        },
        metadata: {
          relations: {
            admin: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'branch',
        relations: {
          bank: {
            this: {},
          },
          account_manager: {
            this: {},
          },
          approved_ip_address_range: {
            this: {},
          },
          approved_timeslot: {
            this: {},
          },
          approved_context: {
            intersection: {
              child: [
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'approved_timeslot',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'user',
                    },
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'approved_ip_address_range',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'user',
                    },
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            bank: { directly_related_user_types: [{ type: 'bank' }] },
            account_manager: { directly_related_user_types: [{ type: 'user' }] },
            approved_ip_address_range: { directly_related_user_types: [{ type: 'ip-address-range' }] },
            approved_timeslot: { directly_related_user_types: [{ type: 'timeslot' }] },
          },
        },
      },
      {
        type: 'account',
        relations: {
          branch: {
            this: {},
          },
          account_manager: {
            tupleToUserset: {
              tupleset: {
                object: '',
                relation: 'branch',
              },
              computedUserset: {
                object: '',
                relation: 'account_manager',
              },
            },
          },
          customer: {
            this: {},
          },
          account_manager_viewer: {
            intersection: {
              child: [
                {
                  computedUserset: {
                    object: '',
                    relation: 'account_manager',
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'branch',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'approved_context',
                    },
                  },
                },
              ],
            },
          },
          viewer: {
            union: {
              child: [
                {
                  computedUserset: {
                    object: '',
                    relation: 'customer',
                  },
                },
                {
                  computedUserset: {
                    object: '',
                    relation: 'account_manager_viewer',
                  },
                },
              ],
            },
          },
          can_view: {
            computedUserset: {
              object: '',
              relation: 'viewer',
            },
          },
        },
        metadata: {
          relations: {
            branch: { directly_related_user_types: [{ type: 'branch' }] },
            customer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'transaction',
        relations: {
          account: {
            this: {},
          },
          can_view: {
            tupleToUserset: {
              tupleset: {
                object: '',
                relation: 'account',
              },
              computedUserset: {
                object: '',
                relation: 'viewer',
              },
            },
          },
        },
        metadata: {
          relations: {
            account: { directly_related_user_types: [{ type: 'account' }] },
          },
        },
      },
      {
        type: 'timeslot',
        relations: {
          user: {
            this: {},
          },
        },
        metadata: {
          relations: {
            user: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'ip-address-range',
        relations: {
          user: {
            this: {},
          },
        },
        metadata: {
          relations: {
            user: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how user groups can be used."
  relatedLinks={[
    {
      title: 'Object to Object Relationships',
      description: "Learn how objects can relate to one another and how that can affect user's access.",
      link: './building-blocks/object-to-object-relationships',
      id: './building-blocks/object-to-object-relationships.mdx',
    },
    {
      title: 'Modeling with Multiple Restrictions',
      description:
        'Learn how to model requiring multiple relationships before users are authorized to perform certain actions.',
      link: './multiple-restrictions',
      id: './multiple-restrictions.mdx',
    },
    {
      title: '{ProductName} API',
      description: 'Details on the Check API in the {ProductName} reference guide.',
      link: '/api/service#Relationship%20Queries/Check',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/contextual-time-based-authorization.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/custom-roles.mdx -->

---
sidebar_position: 8
slug: /modeling/custom-roles
description: Modeling custom and dynamically changing roles in your system
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  Playground,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  WriteRequestViewer,
} from '@components/Docs';

### Custom Roles

<DocumentationNotice />

In this guide you'll learn how to model custom roles in your system using <ProductName format={ProductNameFormat.ProductLink}/>.

For example, a Business-to-Business (B2B) application could allow customers to create their own custom roles on the application to grant their users.

<CardBox title="When to use" appearance="filled">

In many cases, roles would fit in well as relations on an object type, as seen in [Modeling Roles and Permissions](https://github.com/openfga/openfga.dev/blob/main/./roles-and-permissions.mdx). In some cases, however, they may not be enough.

Custom roles are useful when:

- Users of the application are able to create arbitrary sets of roles with different permissions that govern the users' access to objects.
- It is not known beforehand (at the time of Authorization Model creation) what the application roles are.
- The team responsible for building the authorization model is different from the teams responsible for defining roles and access to the application.

</CardBox>

#### Before you start

Before you start this guide, make sure you're familiar with some <ProductConcept /> and know how to develop the things listed below.

<details>
<summary>

##### Initial Model

To start, let's say there is an application with a <ProductConcept section="what-is-a-type" linkName="type" /> called `asset-category`. Users can have view and/or edit access to assets in that category. Any user who can edit can also view.

</summary>

We'll start with the following authorization model showing a system with an `asset-category` type. This type allows users to have view and edit access to it.

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'asset-category',
        relations: {
          viewer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  // Anyone who is an editor is a viewer
                  computedUserset: {
                    relation: 'editor',
                  },
                },
              ],
            },
          },
          editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            editor: { directly_related_user_types: [{ type: 'user' }] },
            viewer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

<hr />

In addition, you'll need to know the following:

##### Modeling Roles and Permissions

You need to know how to add users to groups and grant groups access to resources. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/./user-groups.mdx)

##### Modeling Object-to-Object Relationships

You need to know how to create relationships between objects and how that might affect a user's relationships to those objects. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/./building-blocks/object-to-object-relationships.mdx)

##### Concepts & Configuration Language

- <ProductConcept />
- [Configuration Language](https://github.com/openfga/openfga.dev/blob/main/../configuration-language.mdx)

</details>

<Playground store="custom-roles" />

#### Step By Step

Starting with the authorization model mentioned above, we want to enable users to create their own custom roles, and tie permissions to those roles to our two users and to the permissions on the logo asset category.

For this guide, we'll model a scenario where a certain organization using our app has created an `asset-category` called "logos", and another called "text content".

The company administrator would like to create:

- a **media-manager** role that allows users to **edit** assets in the **logos asset category**
- a **media-viewer** role that allows users to **view** all assets in the **logos asset category**
- a **blog-editor** role that allows users to **edit** all assets in the **text content asset category**
- a **blog-viewer** role that allows users to **view** all assets in the **text content asset category**

Imagine these are what the permissions the roles in one organization using our service are like:

![Image showing custom roles and permissions](https://github.com/openfga/openfga.dev/blob/main/./assets/custom-roles-roles-and-permissions.svg)

Finally, the administrator wants to assign **Anne** the **media-manager** role and **Beth** the **media-viewer** role.

At the end, we'll verify our model by ensuring the following access <ProductConcept section="what-is-a-check-request" linkName="check" /> requests return the expected result.

![Image showing expected results](https://github.com/openfga/openfga.dev/blob/main/./assets/custom-roles-expectations.svg)

In order to do this, we need to:

<ol className="list-numbered-leading-zeros">
  <li>Update the Authorization Model to add a Role Type</li>
  <li>Use Relationship Tuples to tie the Users to the Roles</li>
  <li>Use Relationship Tuples to associate Permissions with the Roles</li>
  <li>Verify that the Authorization Model works</li>
</ol>

##### 01. Update The Authorization Model To Add A Role Type

Because our roles are going to be dynamic and might change frequently, we represent them in a new type instead of as relations on that same type. We'll create new type called `role`, where users can be related as assignee to it.

The authorization model becomes this:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'asset-category',
        relations: {
          viewer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  // Anyone who can edit can view
                  computedUserset: {
                    relation: 'editor',
                  },
                },
              ],
            },
          },
          editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            viewer: { directly_related_user_types: [{ type: 'user' }, { type: 'role', relation: 'assignee' }] },
            editor: { directly_related_user_types: [{ type: 'user' }, { type: 'role', relation: 'assignee' }] },
          },
        },
      },
      {
        type: 'role',
        relations: {
          assignee: {
            this: {},
          },
        },
        metadata: {
          relations: {
            assignee: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

With this change we can add relationship tuples indicating that a certain user is `assigned` a certain `role`.

##### 02.Use Relationship Tuples To Tie The Users To The Roles

Once we've added the `role` type, we can assign roles to Anne and Beth. Anne is assigned the "media-manager" role and Beth is assigned the "media-viewer" role. We can do that by adding relationship tuples as follows:

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'Anne is assigned the media-manager role',
      user: 'user:anne',
      relation: 'assignee',
      object: 'role:media-manager',
    },
    {
      _description: 'Beth is assigned the media-viewer role',
      user: 'user:beth',
      relation: 'assignee',
      object: 'role:media-viewer',
    },
  ]}
/>

We can verify they are members of said roles by issuing the following check requests:

![Image showing expected membership checks](https://github.com/openfga/openfga.dev/blob/main/./assets/custom-roles-membership-checks.svg)

<CheckRequestViewer user={'user:anne'} relation={'assignee'} object={'role:media-manager'} allowed={true} />

##### 03. Use Relationship Tuples To Associate Permissions With The Roles

With our users and roles set up, we still need to tie members of a certain role to it's corresponding permission(s).

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'Users assigned the media-manager role can edit in the Logos assets category',
      user: 'role:media-manager#assignee',
      relation: 'editor',
      object: 'asset-category:logos',
    },
    {
      _description: 'Users assigned the media-viewer role can view from the Logos assets category',
      user: 'role:media-viewer#assignee',
      relation: 'viewer',
      object: 'asset-category:logos',
    },
  ]}
/>

##### 04. Verify That The Authorization Model Works

To ensure our model works, it needs to match our expectations:

![Image showing expected results](https://github.com/openfga/openfga.dev/blob/main/./assets/custom-roles-expectations.svg)

<CheckRequestViewer user={'user:anne'} relation={'editor'} object={'asset-category:logos'} allowed={true} />

The checks come back as we expect, so our model is working correctly.

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to model with {ProductName}."
  relatedLinks={[
    {
      title: 'Modeling Roles and Permissions',
      description: 'Learn how to remove the direct relationship to indicate nonassignable permissions.',
      link: './roles-and-permissions',
      id: './roles-and-permissions.mdx',
    },
    {
      title: 'Modeling Concepts: Object to Object Relationships',
      description: 'Learn about how to model object to object relationships in {ProductName}.',
      link: './building-blocks/object-to-object-relationships',
      id: '../building-blocks/object-to-object-relationships',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/custom-roles.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/direct-access.mdx -->

---
sidebar_position: 1
slug: /modeling/direct-access
description: Granting a user access to an object
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  Playground,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  WriteRequestViewer,
} from '@components/Docs';

### Direct Access

<DocumentationNotice />

This article describes how to grant a <ProductConcept section="what-is-a-user" linkName="user" /> access to an <ProductConcept section="what-is-an-object" linkName="object" /> in <ProductName format={ProductNameFormat.ProductLink}/>.

<CardBox title="When to use" appearance="filled">

Granting access with <ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuples" /> is a core part of <ProductName format={ProductNameFormat.ShortForm}/>. Without relationship tuples, any <ProductConcept section="what-is-a-check-request" linkName="checks" />_ will fail. You should use:

- **authorization model** to represent what relations are possible between the users and objects in the system
- **relationship tuples** to represent the facts about the relationships between users and objects in your system.

</CardBox>

#### Before you start

Familiarize yourself with <ProductConcept/> to understand how to develop a relationship tuple and authorization model.

<details>
<summary>

Assume that you have the following <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />.<br />
You have a <ProductConcept section="what-is-a-type" linkName="type" /> called `document` that can have a `viewer` and/or an `editor`.

</summary>

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          viewer: {
            this: {},
          },
          editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            viewer: { directly_related_user_types: [{ type: 'user' }] },
            editor: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

<hr />

In addition, you will need to know the following:

##### <ProductName format={ProductNameFormat.ShortForm}/> Concepts

- A <ProductConcept section="what-is-a-type" linkName="Type" />: a class of objects that have similar characteristics
- A <ProductConcept section="what-is-a-user" linkName="User" />: an entity in the system that can be related to an object
- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system
- An <ProductConcept section="what-is-an-object" linkName="Object" />: represents an entity in the system. Users' relationships to it can be define through relationship tuples and the authorization model
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a grouping consisting of a user, a relation and an object stored in <ProductName format={ProductNameFormat.ShortForm}/>

</details>

<Playground />

#### Step By Step

For an application to understand that **user x** has access to **document y**, it must provide <ProductName format={ProductNameFormat.LongForm}/> that information with <ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuples" />.
Each relationship tuple has three basic parameters: a **<ProductConcept section="what-is-a-user" linkName="user" />**, a **<ProductConcept section="what-is-a-relation" linkName="relation" />** and an **<ProductConcept section="what-is-an-object" linkName="object" />**.

##### 01. Create A Relationship Tuple

Below, you'll add a **<ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuple" />** to indicate that `bob` is an `editor` of `document:meeting_notes.doc` by adding the following:

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:bob',
      relation: 'editor',
      object: 'document:meeting_notes.doc',
    },
  ]}
/>

##### 02. Check That The Relationship Exists

Once you add that relationship tuple to <ProductName format={ProductNameFormat.ShortForm} />, you can <ProductConcept section="what-is-a-check-request" linkName="check" /> if the relationship is valid by asking if bob is an editor of document:meeting_notes.doc:

<CheckRequestViewer user={'user:bob'} relation={'editor'} object={'document:meeting_notes.doc'} allowed={true} />

Checking whether `bob` is an `viewer` of `document:meeting_notes.doc` returns **false** because that relationship tuple does not exist in <ProductName format={ProductNameFormat.ShortForm}/> yet.

<CheckRequestViewer user={'user:bob'} relation={'viewer'} object={'document:meeting_notes.doc'} allowed={false} />

:::caution
When creating relationship tuples for <ProductName format={ProductNameFormat.LongForm}/>, use unique ids for each object and user within your application domain. We're using first names and simple ids to as an easy-to-follow example.
:::

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to model with {ProductName}."
  relatedLinks={[
    {
      title: '{ProductName} Concepts',
      description: 'Learn about the {ProductName} Concepts.',
      link: '../concepts',
      id: '../fga-concepts',
    },
    {
      title: 'Modeling: Getting Started',
      description: 'Learn about how to get started with modeling.',
      link: './getting-started',
      id: './getting-started',
    },
    {
      title: 'Configuration Language',
      description: 'Learn about {ProductName} Configuration Language.',
      link: '../configuration-language',
      id: '../configuration-language',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/direct-access.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/getting-started.mdx -->

---
title: 'Get Started with Modeling'
description: An introduction to modeling
sidebar_position: 1
slug: /modeling/getting-started
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CardGrid,
  ColumnLayout,
  DocumentationNotice,
  IntroductionSection,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  RelationshipTuplesViewer,
  SyntaxFormat,
  UpdateProductNameInLinks,
} from '@components/Docs';

import DocIcon from '@site/static/img/getting-started-icon-doc.svg';
import DirIcon from '@site/static/img/getting-started-icon-dir.svg';
import OrgIcon from '@site/static/img/getting-started-icon-org.svg';
import DriveIcon from '@site/static/img/getting-started-icon-drive.svg';
import FGAIcon from '@site/static/img/getting-started-fga-logo.svg';

### Get Started with Modeling

<DocumentationNotice />

Creating a <IntroductionSection linkName="Relationship Based Access Control (ReBAC)" section="what-is-relationship-based-access-control"/> authorization model might feel odd at first. Most of us tend to think about authorization models in terms of roles and permissions. After all, most software works like that. Your existing systems are likely built on a model using roles and permissions.

This guide outlines a process for defining your <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" /> with <ProductName format={ProductNameFormat.ProductLink}/>.

You can also check out the [Modeling Guide](https://www.youtube.com/watch?v=5Lwy9aHXXHE&list=PLUR5l-oTFZqWaDdhEOVt_IfPOIbKo1Ypt) on YouTube or the [Samples Repository](https://github.com/openfga/sample-stores).

#### Introduction To Modeling

To define a ReBAC model in <ProductName format={ProductNameFormat.ShortForm}/> we recommend:

- If you have an existing system: forget about how your system works today and start thinking about how you want it to work in the future.
- Thinking about authorization starting from the resources, or objects as <ProductName format={ProductNameFormat.ShortForm}/> calls them.

If that sounds hard, don't worry! We'll guide you through it.

<ProductName format={ProductNameFormat.ShortForm} /> is built to quickly and reliably make <ProductConcept
  section="what-is-a-check-request"
  linkName="authorization checks"
/>
. This means providing an answer to a question: "Can user U perform action A on object O?"

ReBAC systems determine access from a <ProductConcept section="what-is-a-user" linkName="user's" /> <ProductConcept section="what-is-a-relation" linkName="relation" /> to an <ProductConcept section="what-is-an-object" linkName="object" />. Authorization decisions are then yes or no answers to the question: "Does user U have relation R with object O?".

<ColumnLayout cols={2} style={{ marginTop: '2rem', marginBottom: '2rem' }}>
  <CardBox title="General Authorization Check" appearance="filled">
    "Can user <b>U</b> <u>perform an action</u> <b>A</b> on object <b>O</b>?"
  </CardBox>
  <CardBox title="{ProductName} (ReBAC) Authorization Check">
    "Does user <b>U</b> <u>have relation</u> <b>R</b> with object <b>O</b>?"
  </CardBox>
</ColumnLayout>

In the previous example, a relation R should be defined that implies permission to action A. For example:

<ColumnLayout cols={2} style={{ marginTop: '2rem', marginBottom: '2rem' }}>
  <CardBox title="General Authorization Check" appearance="filled">
    "Can user <b>Jane</b> <u>perform action</u> <b>view</b> on object <b>project sandcastle</b>?"
  </CardBox>
  <CardBox title="{ProductName} (ReBAC) Authorization Check">
    "Can user <b>Jane</b> <u>have relation</u> <b>view</b> with object <b>project sandcastle</b>?"
  </CardBox>
</ColumnLayout>

We'll provide more detailed examples throughout this article.

When you are modeling, you need to answer a more general question:

<div style={{ marginTop: '2rem', marginBottom: '2rem' }}>
  <CardBox
    icon={{ icon: <FGAIcon />, alignment: 'left' }}
    title="Why could user U perform an action A on an object O?"
  />
</div>

If you can answer that question for all types of objects in your system, then you can codify that into an authorization model.

Let's get started!

---

#### A Process For Defining Authorization Models

Defining an authorization model requires codifying an answer to the question "why could user U perform an action A on an object O?" for all use cases or actions in your system. This is an iterative process. For the purpose of this guide, we'll go through one iteration of this process using a simplified Google Drive like system as an example.

Steps for defining your authorization model:

1.  [Pick the most important feature](#01-pick-the-most-important-feature)
2.  [List the object types](#02-list-the-object-types)
3.  [List relations for those types](#03-list-relations-for-those-types)
4.  [Define relations](#04-define-relations)
5.  [Test the model](#05-test-the-model)
6.  [Iterate](#06-iterate)

![The starting point](https://github.com/openfga/openfga.dev/blob/main/./assets/getting-started-diagram-01.svg)

##### 01. Pick The Most Important Feature

![Pick the most important feature](https://github.com/openfga/openfga.dev/blob/main/./assets/getting-started-diagram-02.svg)

A feature, in the context of this document, is an action or related set of actions your users can perform in your system. We'll introduce an example feature later in this section.

Start with the most important feature. It doesn't have to be the most complex one, but it should be the most important one. You're probably more familiar with the authorization requirements for this feature than other less important use cases.

:::caution Important

- Requirement clarity is fundamental when defining an authorization model.
- The scope of the feature is not important at this point. You can always iterate later.

:::

###### Write It In Plain Language

Once you've picked a feature, describe its authorization related scope using simple language. Avoid using the word "roles", as this ties you to an RBAC way of thinking.

:::info
Roles don't "disappear" in ReBAC systems like <ProductName format={ProductNameFormat.ShortForm}/>. Your users might [have roles on a given object, rather than the entire system](https://github.com/openfga/openfga.dev/blob/main/./roles-and-permissions.mdx). But starting from the term "role" might lead you down the wrong path. Instead it is better to discover roles while you are modeling.
:::

Your feature description should include the <ProductConcept section="what-is-an-object" linkName="objects" />, <ProductConcept section="what-is-a-user" linkName="users" /> and <ProductConcept section="what-is-a-user" linkName="groups of users" /> participating in the system. Sentences should look like this:

<div style={{ marginBottom: '2rem' }}>
  <CardBox
    title="A user {user} can perform action {action} to/on/in {object types} ... IF {conditions}"
    appearance="filled"
    centerTitle
  />
</div>
Let's look at an example of a simplified Google Drive like system. We'll focus on the feature allowing users to create, read,
update, delete, and share documents with other users.

<br />
<br />

This feature can be described with these sentences:

<CardBox appearance='filled' monoFontChildren>

- A user can create a document in a drive if they are the owner of the drive.
- A user can create a folder in a drive if they are the owner of the drive.
- A user can create a document in a folder if they are the owner of the folder. The folder is the parent of the document.
- A user can create a folder in a folder if they are the owner of the folder. The existing folder is the parent of the new folder.

---

- A user can share a document with another user or an organization as either editor or viewer if they are an owner or editor of a document or if they are an owner of the folder/drive that is the parent of the document.

---

- A user can share a folder with another user or an organization as a viewer if they are an owner of the folder.

---

- A user can view a document if they are an owner, viewer or editor of the document or if they are a viewer or owner of the folder/drive that is the parent of the document.

---

- A user can edit a document if they are an owner or editor of the document or if they are an owner of the folder/drive that is the parent of the document.

---

- A user can change the owner of a document if they are an owner of the document.

---

- A user can change the owner of a folder if they are an owner of the folder.

---

- A user can be a member of an organization.

  <span style={{ color: 'gray' }}>
    How a user is added as a member to an organization is beyond the scope of the feature we picked to write down.
  </span>

---

- A user can view a folder if they are the owner of the folder, or a viewer or owner of either the parent folder of the folder, or the parent drive of the folder.

</CardBox>

##### 02. List The Object Types

![List the object types](https://github.com/openfga/openfga.dev/blob/main/./assets/getting-started-diagram-03.svg)

Next make a list of the <ProductConcept section="what-is-a-type" linkName="types" /> of objects in your system. You might be able to identify the objects in your system from your existing domain/database model.

Find all the objects in the previous step using this template:

<div style={{ marginBottom: '2rem' }}>
  <CardBox
    title="A user {user} can perform action {action} to/on/in {object type} ... IF {conditions}"
    appearance="filled"
    centerTitle
  />
</div>

These are all the object types from the previous step (in order of appearance) based on that template:

<ColumnLayout cols={3} style={{ marginTop: '2rem', marginBottom: '2rem' }}>
  <CardBox icon={{ icon: <DocIcon />, alignment: 'left', label: 'Document' }} />
  <CardBox icon={{ icon: <DirIcon />, alignment: 'left', label: 'Folder' }} />
  <CardBox icon={{ icon: <OrgIcon />, alignment: 'left', label: 'Organization' }} />
</ColumnLayout>

Let's highlight all object types in <span className="blue-highlight-text">blue</span>:

<div style={{ marginTop: '2rem', marginBottom: '2rem' }}>
<CardBox appearance='filled' monoFontChildren>

- A user can create a <span className="blue-highlight-text">document</span> in a drive if they are the owner of the drive.
- A user can create a <span className="blue-highlight-text">folder</span> in a drive if they are the owner of the drive.
- A user can create a <span className="blue-highlight-text">document</span> in a folder if they are the owner of the folder.
- A user can create a <span className="blue-highlight-text">folder</span> in a folder if they are the owner of the folder.

---

- A user can share a <span className="blue-highlight-text">document</span> with another user or an organization as either editor or viewer if they are an owner or editor of a document or if they are an owner of the folder/drive that is the parent of the document.

---

- A user can share a <span className="blue-highlight-text">folder</span> with another user or an organization as a viewer if they are an owner of the folder.

---

- A user can view a <span className="blue-highlight-text">document</span> if they are an owner, viewer or editor of the document or if they are a viewer, owner of the folder/drive that is the parent of the document.

---

- A user can edit a <span className="blue-highlight-text">document</span> if they are an owner or editor of the document or if they are an owner of the folder/drive that is the parent of the document.

---

- A user can change the owner of a <span className="blue-highlight-text">document</span> if they are an owner of the document.

---

- A user can change the owner of a <span className="blue-highlight-text">folder</span> if they are an owner of the folder.

---

- A user can be a member of an <span className="blue-highlight-text">organization</span>.

  <span style={{ color: 'gray' }}>
    How a user is added as a member to an organization is beyond the scope of the feature we picked to write down.
  </span>

---

- A user can view a <span className="blue-highlight-text">folder</span> if they are the owner of the folder, or a viewer or owner of either the parent folder of the folder, or the parent drive of the folder.

</CardBox>
</div>

However, the list of object types is not finished. To complete the list of object types you must also add all the second nouns that appear in conditions as part of expressions of this format: **"\{first noun\} of a/the \{second noun\}"**.

<div style={{ marginBottom: '2rem' }}>
  <CardBox centerTitle title="... IF {first noun} of a/the {second noun}" appearance="filled" />
</div>

Let's highlight those expressions in <span className="green-highlight-text">green</span>:

<div style={{ marginTop: '2rem', marginBottom: '2rem' }}>
<CardBox monoFontChildren appearance='filled'>

- A user can create a document in a drive if they are the <span className="green-highlight-text">owner of the drive</span>.
- A user can create a folder in a drive if they are the <span className="green-highlight-text">owner of the drive</span>.
- A user can create a document in a folder if they are the <span className="green-highlight-text">owner of the folder</span>. The folder is the <span className="green-highlight-text">parent of the document</span>.
- A user can create a folder in a folder if they are the <span className="green-highlight-text">owner of the folder</span>. The existing folder is the <span className="green-highlight-text">parent of the new folder</span> .

---

- A user can share a document with another user or an organization as either editor or viewer if they are an <span className="green-highlight-text">owner or editor of a document</span> or if they are an <span className="green-highlight-text">owner of the folder/drive</span> that is the <span className="green-highlight-text">parent of the document</span>.

---

- A user can share a folder with another user or an organization as a viewer if they are an <span className="green-highlight-text">owner of the folder</span>.

---

- A user can view a document if they are an <span className="green-highlight-text">owner, viewer or editor of the document</span> or if they are a <span className="green-highlight-text">viewer or owner of the folder/drive</span> that is the <span className="green-highlight-text">parent of the document</span>.

---

- A user can edit a document if they are an <span className="green-highlight-text">owner or editor of the document</span> or if they are an <span className="green-highlight-text">owner of the folder/drive</span> that is the <span className="green-highlight-text">parent of the document</span>.

---

- A user can change the owner of a document if they are an <span className="green-highlight-text">owner of the document</span>.

---

- A user can change the owner of a folder if they are an <span className="green-highlight-text">owner of the folder</span>.

---

- A user can be a member of an organization.

  <span style={{ color: 'gray' }}>
    How a user is added as a member to an organization is beyond the scope of the feature we picked to write down.
  </span>

---

- A user can view a folder if they are the <span className="green-highlight-text">owner of the folder</span>, or a <span className="green-highlight-text">viewer or owner of either the parent folder of the folder, or the parent drive of the folder</span>.

</CardBox>
</div>

The only second noun we didn't have in our object type list is "Drive", so we'll add it to the list.
We will also need to add "User" to the list as it establishes the type of user who can establish relations.

<ColumnLayout cols={5} style={{ marginTop: '2rem', marginBottom: '2rem' }}>
  <CardBox icon={{ alignment: 'left', label: 'User' }} />
  <CardBox icon={{ icon: <DocIcon />, alignment: 'left', label: 'Document' }} />
  <CardBox icon={{ icon: <DirIcon />, alignment: 'left', label: 'Folder' }} />
  <CardBox icon={{ icon: <OrgIcon />, alignment: 'left', label: 'Organization' }} />
  <CardBox icon={{ icon: <DriveIcon />, alignment: 'left', label: 'Drive' }} />
</ColumnLayout>

Now that we have a list of object types we can start defining them using the <UpdateProductNameInLinks link="../configuration-language" name="{ProductName} Configuration Language" />:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
      },
      {
        type: 'folder',
      },
      {
        type: 'organization',
      },
      {
        type: 'drive',
      },
    ],
  }}
/>

:::info Caution
You're now in the process of building a version you can use. The model above is not yet a valid authorization model accepted by <ProductName format={ProductNameFormat.ShortForm}/>.
:::

:::info Important
In a few cases other users can be part of determining whether an action can be performed on an object or not. Social media is an example of this "a user can comment on a picture if they are a friend of the user that published it".

In those cases [**User** should also be an object type](https://github.com/openfga/openfga.dev/blob/main/./building-blocks/object-to-object-relationships.mdx). Following the last recommendation, we would discover the User type because it is a second noun in an expression: "friend of the user".
:::

##### 03. List Relations For Those Types

![List relations for those types](https://github.com/openfga/openfga.dev/blob/main/./assets/getting-started-diagram-04.svg)

Each of the previously defined types has a set of relations. <ProductConcept section="what-is-a-relation" linkName="Relations" /> are an important component in your model. After all, <ProductName format={ProductNameFormat.ShortForm}/> is a <IntroductionSection linkName="Relationship Based Access Control (ReBAC)" section="what-is-relationship-based-access-control-rebac"/> system.

To identify relations for a type in the write-up we can perform an exercise similar to the one we did in [list the type of objects in your system](#02-list-the-object-types).

Relations for a type \{type\} will be all of these:

- any noun that is the \{noun\} of a "\{noun\} of a/an/the \{type\}" expression. **These are typically the Foreign Keys in a database.** We'll highlight these in <span className="green-highlight-text">green</span>.
- any verb or action that is the \{action\} of a "can \{action\} (in) a/an \{type\}" expression. **These are typically the permissions for a type.** We'll highlight these in <span className="yellow-highlight-text">yellow</span>.

<div style={{ marginTop: '2rem', marginBottom: '2rem' }}>
<CardBox monoFontChildren appearance='filled'>

- A user <span className="yellow-highlight-text">can create a document in a drive</span> if they are the <span className="green-highlight-text">owner of the drive</span>.
- A user <span className="yellow-highlight-text">can create a folder in a drive</span> if they are the <span className="green-highlight-text">owner of the drive</span>.
- A user <span className="yellow-highlight-text">can create a document in a folder</span> if they are the <span className="green-highlight-text">owner of the folder</span>. The folder is the <span className="green-highlight-text">parent of the document</span>.
- A user <span className="yellow-highlight-text">can create a folder in a folder</span> if they are the <span className="green-highlight-text">owner of the folder</span>. The existing folder is the <span className="green-highlight-text">parent of the new folder</span>.

---

- A user <span className="yellow-highlight-text">can share a document with another user or an organization</span> as either editor or viewer if they are an <span className="green-highlight-text">owner or editor of a document</span> or if they are an <span className="green-highlight-text">owner of the folder/drive</span> that is the <span className="green-highlight-text">parent of the document</span>.

---

- A user <span className="yellow-highlight-text">can share a folder with another user or an organization</span> as a viewer if they are an <span className="green-highlight-text">owner of the folder</span>.

---

- A user <span className="yellow-highlight-text">can view a document</span> if they are an <span className="green-highlight-text">owner, viewer or editor of the document</span> or if they are a <span className="green-highlight-text">viewer or owner of the folder/drive</span> that is the <span className="green-highlight-text">parent of the document</span>.

---

- A user <span className="yellow-highlight-text">can edit a document</span> if they are an <span className="green-highlight-text">owner or editor of the document</span> or if they are an <span className="green-highlight-text">owner of the folder/drive</span> that is the <span className="green-highlight-text">parent of the document</span>.

---

- A user <span className="yellow-highlight-text">can change the owner of a document</span> if they are an <span className="green-highlight-text">owner of the document</span>.

---

- A user <span className="yellow-highlight-text">can change the owner of a folder</span> if they are an <span className="green-highlight-text">owner of the folder</span>.

---

- A user <span className="yellow-highlight-text">can be a member of an organization</span>.

  <span style={{ color: 'gray' }}>
    How a user is added as a member to an organization is beyond the scope of the feature we picked to write down.
  </span>

---

- A user <span className="yellow-highlight-text">can view a folder</span> if they are the <span className="green-highlight-text">owner of the folder</span>, or a <span className="green-highlight-text">viewer or owner of either the parent folder of the folder, or the parent drive of the folder</span>.

</CardBox>
</div>

The resulting list is:

<ColumnLayout cols={4} equalWidth style={{ marginTop: '2rem', marginBottom: '2rem' }}>
<CardBox
  smallFontChildren
  icon={{ icon: <DocIcon />, alignment: 'middle', label: 'Document' }}
>

- parent
- can_share
- owner
- editor
- can_write
- can_view
- viewer
- can_change_owner

</CardBox>
<CardBox
  smallFontChildren
  icon={{ icon: <DirIcon />, alignment: 'middle', label: 'Folder' }}
>

- can_create_document
- owner
- can_create_folder
- can_view
- viewer
- parent

</CardBox>
<CardBox
  smallFontChildren
  icon={{ icon: <OrgIcon />, alignment: 'middle', label: 'Organization' }}
>

- member

</CardBox>
<CardBox
  smallFontChildren
  icon={{ icon: <DriveIcon />, alignment: 'middle', label: 'Drive' }}
>

- can_create_document
- owner
- can_create_folder

</CardBox>
</ColumnLayout>

:::info
In <ProductName format={ProductNameFormat.ShortForm}/>, relations can only have alphanumeric characters, underscores and hyphens. We recommend using underscore (\_) to separate words and removing prepositions. E.g.: "can create a document" can become "can_create_document" or "create_document" if you are into brevity.
:::

Using the <UpdateProductNameInLinks link="../configuration-language" name="{ProductName} Configuration Language" /> we can enumerate the relations for each type:

```dsl.openfga
model
  schema 1.1
type user
type document
  relations
    define parent:
    define owner:
    define editor:
    define viewer:
    define can_share:
    define can_view:
    define can_write:
    define can_change_owner:
type folder
  relations
    define owner:
    define parent:
    define viewer:
    define can_create_folder:
    define can_create_document:
    define can_view:
type organization
  relations
    define member:
type drive
  relations
    define owner:
    define can_create_document:
    define can_create_folder:
```

:::info Caution
You're now in the process of building a version you can use. The model above is not yet a valid authorization model accepted by <ProductName format={ProductNameFormat.ShortForm}/>.
:::

##### 04. Define Relations

![Define relations](https://github.com/openfga/openfga.dev/blob/main/./assets/getting-started-diagram-05.svg)

We will use the <UpdateProductNameInLinks link="../configuration-language" name="{ProductName} Configuration Language" /> to create a <ProductConcept section="what-is-a-relation" linkName="relation definition" /> for each of the relations we identified. At this stage we will encode the answers to the question we asked at the beginning of the document:.

<div style={{ marginBottom: '2rem' }}>
  <CardBox
    icon={{ icon: <FGAIcon />, alignment: 'left' }}
    title="Why could a user U, perform an action A on an object O?"
  />
</div>

We are going to go over each type and each of its relations and create a definition for it.

<div style={{ display: 'inline-block' }}>
<OrgIcon />
<div style={{ float: 'right', marginLeft: '16px' }}>

###### Type: Organization

</div>
</div>

We recommend starting from objects that represent groups/containers of users. For features in most systems these are easy to define and help reason about the other types. Examples of type names for these are "team", "group", "organization", etc.

###### Relation: Member

The member relation is used to tell <ProductName format={ProductNameFormat.ShortForm}/> about the members of an organization.

:::info Important
Relation names in <ProductName format={ProductNameFormat.ShortForm}/> are arbitrary strings. There are no reserved relation names. You can use "member" or "part_of" or anything else to refer to a user that is part of a team/organization.
:::

Remember _"How a user is added as a member to an organization is beyond the scope of this feature."_ For the purposes of this model the relation definition should be:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'organization',
        relations: {
          member: { this: {} },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }, { type: 'organization', relation: 'member' }] },
          },
        },
      },
    ],
  }}
  skipVersion={true}
/>

Why? This relation definition states:

- That organizations have members
- That the members of an organization with id \{id\} are all users described by tuples of the form:

  `{ user: {user-id}, relation: "member", object: "organization:{id}" }`

:::info Important
Relation definitions of the form "define \{relation\}: [user, organization#member]" are fairly common. They are used to express that relationships "to the object with that relation" (e.g. "users" of type user or "member of organization") can be assigned by your system and that only the users that have that relation are those with a [direct relationship](https://github.com/openfga/openfga.dev/blob/main/./building-blocks/direct-relationships.mdx).
:::

You can read more about group membership and types in [Modeling User Groups](https://github.com/openfga/openfga.dev/blob/main/./user-groups.mdx).

For the direct relationships, we need to figure out the object types that makes sense for the relationship tuples' user. In our organization example, it makes sense for member relations to have user of type

- user
- organization#member (i.e., other organization's member)

However, it will not make sense for organization member's user to be of type document, folder or drive.

We will specify this logic as part of <ProductConcept section="what-is-a-directly-related-user-type" linkName="directly related user type" />.

:::note Side note
This also automatically supports nested organizational membership if you want such a feature in your system. You could use relationship tuples like the following one to express that "members of organization A are members of organization B":

```
{ user: "organization:A#member", relation: "member", object: "organization:B"}
```

If you want to learn more, you can read further about this in [Modeling User Groups](https://github.com/openfga/openfga.dev/blob/main/./user-groups.mdx) and [Managing Relationships Between Objects](https://github.com/openfga/openfga.dev/blob/main/../interacting/managing-relationships-between-objects.mdx).
:::

###### Complete Type Definition

The complete <ProductConcept section="what-is-a-type-definition" linkName="type definition" /> for the **organization** type is:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'organization',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }, { type: 'organization', relation: 'member' }] },
          },
        },
      },
    ],
  }}
  skipVersion={true}
/>

<div style={{ display: 'inline-block' }}>
<DocIcon />
<div style={{ float: 'right', marginLeft: '16px' }}>

###### Type: Document

</div>
</div>

After defining your "group" like types, continue with the most important type for the feature: the one that allows the main use case. In this case "document", since the main use case for users is to create, write, read and collaborate on documents.

Defining relations for the main type lets you focus on your core use case, and will likely make other type definitions easier.

###### Relation: Owner

The owner relation is used to tell <ProductName format={ProductNameFormat.ShortForm}/> which users are owners of the document.

:::info Important
In the current version, there is no way to state that there is only one owner in the authorization model. The application must limit this <ProductConcept section="what-is-a-user" linkName="set of users" /> to just one owner if that is a requirement.
:::

When a document is created, a relationship tuple will be stored in <ProductName format={ProductNameFormat.ShortForm}/> representing this relationship between owner and document. This is an example of a [user to object relationship](https://github.com/openfga/openfga.dev/blob/main/./direct-access.mdx).

The relation definition then should be:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'document',
        relations: {
          owner: { this: {} },
        },
        metadata: {
          relations: {
            owner: { directly_related_user_types: [{ type: 'user' }, { type: 'organization', relation: 'member' }] },
          },
        },
      },
    ],
  }}
  skipVersion={true}
/>

Why? This <ProductConcept section="what-is-a-relation" linkName="relation definition" /> states that:

- each document can have one or more owners
- owners of a document are assignable by creating a tuple of the format
  `{ user: "{user_id}", relation: "owner", object: "document:{id}" }` for individual users

###### Relation: Editor

The editor relation is used to tell <ProductName format={ProductNameFormat.ShortForm}/> which users are editors of the document.

When a user shares a document with another user or set of users as editor, a relationship tuple will be stored in <ProductName format={ProductNameFormat.ShortForm}/> representing this relationship between editor and document. This is an example of a [users to object relationship](https://github.com/openfga/openfga.dev/blob/main/./direct-access.mdx).

The relation definition then should be:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'document',
        relations: {
          editor: { this: {} },
        },
        metadata: {
          relations: {
            editor: { directly_related_user_types: [{ type: 'user' }, { type: 'organization', relation: 'member' }] },
          },
        },
      },
    ],
  }}
  skipVersion={true}
/>

Why? This relation definition states that:

- each document can have editors
- the editor(s) of a document are assignable by creating a tuple with shape
  `{ user: "{user_id}", relation: "editor", object: "document:{id}" }` for individual users

This also supports making all members in an organization editors of the document, through a [group to object relationship](https://github.com/openfga/openfga.dev/blob/main/./user-groups.mdx). A relationship tuple like the following one states that the members of organization A are editors of document 0001.

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      user: 'organization:A#member',
      relation: 'editor',
      object: 'document:0001',
    },
  ]}
/>

You can learn more about this in [Modeling User Groups](https://github.com/openfga/openfga.dev/blob/main/./user-groups.mdx).

###### Relation: Viewer

The viewer relation is similar to the document's [editor relation](#relation-editor). It will be defined like this:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'document',
        relations: {
          viewer: { this: {} },
        },
        metadata: {
          relations: {
            viewer: { directly_related_user_types: [{ type: 'user' }, { type: 'organization', relation: 'member' }] },
          },
        },
      },
    ],
  }}
  skipVersion={true}
/>

###### Relation: Parent

The parent relation is used to tell <ProductName format={ProductNameFormat.ShortForm}/> which folder or drive is the parent of the document.

:::caution Important
Relation names in <ProductName format={ProductNameFormat.ShortForm}/> are arbitrary strings. There are no reserved relation names. You can use "parent", "container" or "ancestor" to refer to a "parent folder".
:::

This relation is different from the others we have seen so far, as it is a relation between two objects (a **folder** and or **drive** that is the parent of the **document**). This is known as an [object to object relationship](https://github.com/openfga/openfga.dev/blob/main/./building-blocks/object-to-object-relationships.mdx), of which [parent-child is a particular case](https://github.com/openfga/openfga.dev/blob/main/./parent-child.mdx).

When a document is created a relationship tuple will be stored in <ProductName format={ProductNameFormat.ShortForm}/> to represent this relationship between parent and document. The relation definition then should be:

<AuthzModelSnippetViewer
  configuration={
      {
        type: 'document',
        relations: {
          parent: { this: {} },
        },
        metadata: {
          relations: {
            parent: { directly_related_user_types: [{ type: 'folder' }, { type: 'drive' }] },
          },
        },
  }}
  skipVersion={true}
/>

Why? This relation definition states that:

- documents may have a parent
- the parent(s) of a document with id \{id\} is either a folder or a drive, described by one of these relationship tuples:
  - `{ user: "folder:{id}", relation: "parent", object: "document:{id}" }`
  - `{ user: "drive:{id}", relation: "parent", object: "document:{id}" }`


We can use [direct type restriction](https://github.com/openfga/openfga.dev/blob/main/../configuration-language.mdx#direct-relationship-type-restrictions) to ensure a document's parent can only be an object of type either drive or folder.

:::note Side note
You might have noticed that the "user" in the tuple is an object. This is a special syntax <ProductName format={ProductNameFormat.ShortForm}/> accepts in the "user" parameter to write [object to object relationships](https://github.com/openfga/openfga.dev/blob/main/./building-blocks/object-to-object-relationships.mdx). You can read more about writing data to manage object to object relationships in [Managing Relationships Between Objects](https://github.com/openfga/openfga.dev/blob/main/../interacting/managing-relationships-between-objects.mdx).
:::

###### Relation: can_share

We need to express the following in the <ProductConcept section="what-is-a-relation" linkName="relation definition" />:

_A user can share a document with another user or an organization as either editor or viewer if they are an owner or editor of a document or if they are an owner of the folder that is the parent of the document._

We can achieve that with the following definition using <UpdateProductNameInLinks link="../configuration-language" name="{ProductName} Configuration Language" />:

<AuthzModelSnippetViewer
  skipVersion={true}
  configuration={
    {
      type: 'document',
      relations: {
        can_share: {
          union: {
            child: [
              {
                computedUserset: {
                  object: '',
                  relation: 'owner',
                },
              },
              {
                computedUserset: {
                  object: '',
                  relation: 'editor',
                },
              },
              {
                tupleToUserset: {
                  computedUserset: {
                    object: '',
                    relation: 'owner',
                  },
                  tupleset: {
                    object: '',
                    relation: 'parent',
                  },
                },
              },
            ],
          },
        },
      }
  }}
/>

There are a few key things here:

- **We don't use a [direct relationship type restriction](https://github.com/openfga/openfga.dev/blob/main/../configuration-language.mdx#direct-relationship-type-restrictions) as part of the definition.** can_share is a common example of representing a permission that is defined in terms of other relations but is not directly assignable by the system.
- The relation definition contains a [union operator](https://github.com/openfga/openfga.dev/blob/main/../configuration-language.mdx#the-union-operator) separating a list of relations that the user must have with the object in order to "be able to share the document". It is any of:
  - Being an owner of the document
  - Being an editor of the document
  - Being an owner of the parent of the document. Whether the parent is a drive or a folder is not important, as they both have an owner relation.

You can read more about the aforementioned items in [Modeling Roles and Permissions](https://github.com/openfga/openfga.dev/blob/main/./roles-and-permissions.mdx).

###### Relation: can_view

We need to express the following in the <ProductConcept section="what-is-a-relation" linkName="relation definition" />:

_A user can view a document if they are an owner, viewer or editor of a document or if they are a viewer, owner of the folder/drive that is the parent of the document._

Similar to the [can_share relation](#relation-can_share), we can achieve that with the following definition using <UpdateProductNameInLinks link="../configuration-language" name="{ProductName} Configuration Language" />:

<AuthzModelSnippetViewer
  skipVersion={true}
  configuration={{
    type: 'document',
    relations:
      {
        can_view: {
          union: {
            child: [
              {
                computedUserset: {
                  object: '',
                  relation: 'viewer',
                },
              },
              {
                computedUserset: {
                  object: '',
                  relation: 'editor',
                },
              },
              {
                computedUserset: {
                  object: '',
                  relation: 'owner',
                },
              },
              {
                tupleToUserset: {
                  computedUserset: {
                    object: '',
                    relation: 'viewer',
                  },
                  tupleset: {
                    object: '',
                    relation: 'parent',
                  },
                },
              },
              {
                tupleToUserset: {
                  computedUserset: {
                    object: '',
                    relation: 'owner',
                  },
                  tupleset: {
                    object: '',
                    relation: 'parent',
                  },
                },
              },
            ],
          },
        },
      },
  }}
/>

###### Relation: can_write

We need to express the following in the <ProductConcept section="what-is-a-relation" linkName="relation definition" />:

_A user can write a document if they are an owner or editor of a document or if they are an owner or editor of the folder/drive that is the parent of the document._

Similar to the [can_share relation](#relation-can_share), we can achieve that with the following definition using <UpdateProductNameInLinks link="../configuration-language" name="{ProductName} Configuration Language" />:

<AuthzModelSnippetViewer
  skipVersion={true}
  configuration={{
    type: 'document',
    relations:
      {
        can_write: {
          union: {
            child: [
              {
                computedUserset: {
                  object: '',
                  relation: 'editor',
                },
              },
              {
                computedUserset: {
                  object: '',
                  relation: 'owner',
                },
              },
              {
                tupleToUserset: {
                  computedUserset: {
                    object: '',
                    relation: 'owner',
                  },
                  tupleset: {
                    object: '',
                    relation: 'parent',
                  },
                },
              },
            ],
          },
        },
      },
  }}
/>

###### Relation: can_change_owner

We need to express the following in the <ProductConcept section="what-is-a-relation" linkName="relation definition" />:

_A user can change the owner of a document if they are an owner of the document._

Similar to the [can_share relation](#relation-can_share), we can achieve that with the following definition using <UpdateProductNameInLinks link="../configuration-language" name="{ProductName} Configuration Language" />:

<AuthzModelSnippetViewer
  skipVersion={true}
  configuration={{
    type: 'document',
    relations:
      {
        can_change_owner: {
          computedUserset: {
            object: '',
            relation: 'owner',
          },
        },
      },
  }}
/>

###### Complete Type Definition

The complete <ProductConcept section="what-is-a-type-definition" linkName="type definition" /> for the document type is:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'document',
        relations: {
          owner: {
            this: {},
          },
          editor: {
            this: {},
          },
          viewer: {
            this: {},
          },
          parent: {
            this: {},
          },
          can_share: {
            union: {
              child: [
                {
                  computedUserset: {
                    object: '',
                    relation: 'owner',
                  },
                },
                {
                  computedUserset: {
                    object: '',
                    relation: 'editor',
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'parent',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'owner',
                    },
                  },
                },
              ],
            },
          },
          can_view: {
            union: {
              child: [
                {
                  computedUserset: {
                    object: '',
                    relation: 'viewer',
                  },
                },
                {
                  computedUserset: {
                    object: '',
                    relation: 'editor',
                  },
                },
                {
                  computedUserset: {
                    object: '',
                    relation: 'owner',
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'parent',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'viewer',
                    },
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'parent',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'editor',
                    },
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'parent',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'owner',
                    },
                  },
                },
              ],
            },
          },
          can_write: {
            union: {
              child: [
                {
                  computedUserset: {
                    object: '',
                    relation: 'editor',
                  },
                },
                {
                  computedUserset: {
                    object: '',
                    relation: 'owner',
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'parent',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'owner',
                    },
                  },
                },
              ],
            },
          },
          can_change_owner: {
            computedUserset: {
              object: '',
              relation: 'owner',
            },
          },
        },
        metadata: {
          relations: {
            owner: { directly_related_user_types: [{ type: 'user' }, { type: 'organization', relation: 'member' }] },
            editor: { directly_related_user_types: [{ type: 'user' }, { type: 'organization', relation: 'member' }] },
            viewer: { directly_related_user_types: [{ type: 'user' }, { type: 'organization', relation: 'member' }] },
            parent: { directly_related_user_types: [{ type: 'folder' }] },
          },
        },
      },
    ],
  }}
/>

Combining the type definitions for document and organization, we have

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'organization',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }, { type: 'organization', relation: 'member' }] },
          },
        },
      },
      {
        type: 'document',
        relations: {
          owner: {
            this: {},
          },
          editor: {
            this: {},
          },
          viewer: {
            this: {},
          },
          parent: {
            this: {},
          },
          can_share: {
            union: {
              child: [
                {
                  computedUserset: {
                    object: '',
                    relation: 'owner',
                  },
                },
                {
                  computedUserset: {
                    object: '',
                    relation: 'editor',
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'parent',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'owner',
                    },
                  },
                },
              ],
            },
          },
          can_view: {
            union: {
              child: [
                {
                  computedUserset: {
                    object: '',
                    relation: 'viewer',
                  },
                },
                {
                  computedUserset: {
                    object: '',
                    relation: 'editor',
                  },
                },
                {
                  computedUserset: {
                    object: '',
                    relation: 'owner',
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'parent',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'viewer',
                    },
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'parent',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'editor',
                    },
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'parent',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'owner',
                    },
                  },
                },
              ],
            },
          },
          can_write: {
            union: {
              child: [
                {
                  computedUserset: {
                    object: '',
                    relation: 'editor',
                  },
                },
                {
                  computedUserset: {
                    object: '',
                    relation: 'owner',
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'parent',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'owner',
                    },
                  },
                },
              ],
            },
          },
          can_change_owner: {
            computedUserset: {
              object: '',
              relation: 'owner',
            },
          },
        },
        metadata: {
          relations: {
            owner: { directly_related_user_types: [{ type: 'user' }, { type: 'organization', relation: 'member' }] },
            editor: { directly_related_user_types: [{ type: 'user' }, { type: 'organization', relation: 'member' }] },
            viewer: { directly_related_user_types: [{ type: 'user' }, { type: 'organization', relation: 'member' }] },
            parent: { directly_related_user_types: [{ type: 'folder' }] },
          },
        },
      },
    ],
  }}
/>

:::note

The <ProductName /> authorization model API and SDK only accepts JSON in its input. To convert from DSL to JSON, you may use the [FGA CLI](https://github.com/openfga/cli) to run `fga model transform`.

:::

##### 05. Test The Model

![Test the model](https://github.com/openfga/openfga.dev/blob/main/./assets/getting-started-diagram-06.svg)

Once you have defined your group like types and the most important type for your feature you want to ensure everything is working as expected. This means testing the model.

How? Remember from the introduction that **<ProductName format={ProductNameFormat.LongForm}/>'s** main job is to answer the question:

<div style={{ marginTop: '2rem', marginBottom: '2rem' }}>
  <CardBox icon={{ icon: <FGAIcon />, alignment: 'left' }} title="Can user U, perform an action A on an object O?" />
</div>

The <ProductName format={ProductNameFormat.ShortForm}/> service does that by checking if a user has a particular relationship to an object, based on your authorization model and relationship tuples.

<ColumnLayout cols={2} style={{ marginTop: '2rem', marginBottom: '2rem' }}>
  <CardBox title="General Authorization Check" appearance="filled">
    "Can user <b>U</b> <u>perform action</u> <b>A</b> on object <b>O</b>?"
  </CardBox>
  <CardBox title="{ProductName} (ReBAC) Authorization Check">
    "Can user <b>U</b> <u>have relation</u> <b>R</b> with object <b>O</b>?"
  </CardBox>
</ColumnLayout>

What we want is to ensure that given our current authorization model and some sample relationship tuples, we get the expected results for those questions.

So we'll write some relationship tuples and assertions. An <ProductName format={ProductNameFormat.ShortForm}/> assertion takes one of these forms:

1. user U **has** relation R with object O
2. user U **does not have** relation R with object O

Much like automated tests and assertions work for programming languages, you can use assertions to prevent regressions while you change your tuples and authorization model. Essentially, assertions help you ensure things work like you expect them to work as you iterate.

###### Write Relationship Tuples

The relationship tuples should represent real examples from your system with fake data.

At this point you haven't defined the drive or folder types, so you can only test things based on users or organization members' relationships to documents. Let's imagine an example setup and write the relationship tuples for it:

| System Action                                                                   | Relationship Tuple                                                                 |
| ------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| Anne is a member of the contoso organization                                    | `{ user:"user:anne", relation: "member", object: "organization:contoso"}`          |
| Beth is a member of fabrikam organization                                       | `{ user:"user:beth", relation: "member", object: "organization:fabrikam"}`         |
| Anne creates document:1, becomes its owner.                                     | `{ user:"user:anne", relation: "owner", object: "document:1"}`                     |
| Anne shares document:1 with all members of the fabrikam organization as editor. | `{ user:"organization:fabrikam#member", relation: "editor", object: "document:1"}` |
| Beth creates document:2 and becomes its owner.                                  | `{ user:"user:beth", relation: "owner", object: "document:2"}`                     |
| Beth shares document:2 with all members of the contoso organization as viewer   | `{ user:"organization:contoso#member", relation: "viewer", object: "document:2"}`  |

Follow these steps to create relationship tuples.

###### Create Assertions

According to our [written down model](#write-it-in-plain-language) and the [relationship tuples](#write-relationship-tuples) from the previous step, these assertions should be specified:

Because anne is the owner of document:1:

- user **anne** has relation **can_share** with document:1
- user **anne** has relation **can_write** with document:1
- user **anne** has relation **can_view** with document:1
- user **anne** has relation **can_change_owner** with document:1

Because beth is a member of organization:fabrikam and members of organization:fabrikam are writer of document:1:

- user **beth** has relation **can_share** with document:1
- user **beth** has relation **can_write** with document:1
- user **beth** has relation **can_view** with document:1
- user **beth** does not have relation **can_change_owner** with document:1

Because beth is the owner of document:2:

- user **beth** has relation **can_share** with document:2
- user **beth** has relation **can_write** with document:2
- user **beth** has relation **can_view** with document:2
- user **beth** has relation **can_change_owner** with document:2

Because anne is a member of organization:contoso and members of organization:contoso are viewer of document:2:

- user **anne** does not have relation **can_share** with document:2
- user **anne** does not have relation **can_write** with document:2
- user **anne** has relation **can_view** with document:2
- user **anne** does not have relation **can_change_owner** with document:2

Follow these steps to create assertions.

###### Run Assertions

Run the assertions. They should all pass. If they don't you can use the query view to understand what is causing them to fail, and then update your authorization model and relation tuples accordingly.

Once all the assertions are working, you should continue the iterative process of working on your model.

##### 06. Iterate

![Iterate](https://github.com/openfga/openfga.dev/blob/main/./assets/getting-started-diagram-07.svg)

We'll leave the exercise of defining the drive and folder relations, then adding relationship tuples and assertions to you. Once you are finished, check out the complete example to see how you did.

When defining the authorization model for your own system, you would continue iterating on the <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" /> with the [next feature](#01-pick-the-most-important-feature) and so on.

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to model with {ProductName}."
  relatedLinks={[
    {
      title: '{ProductName} Concepts',
      description: 'Learn about the {ProductName} Concepts.',
      link: '../concepts',
      id: '../concepts',
    },
    {
      title: 'Configuration Language',
      description: 'Learn about {ProductName} Configuration Language.',
      link: '../configuration-language',
      id: '../configuration-language',
    },
    {
      title: 'Direct Access',
      description: 'Learn about modeling user access to an object.',
      link: './direct-access',
      id: './direct-access',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/getting-started.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/modular-models.mdx -->

---
sidebar_position: 6
slug: /modeling/modular-models
description: Modular Models
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  Playground,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  RelationshipTuplesViewer,
  SyntaxFormat,
  WriteRequestViewer,
  SupportedLanguage,
} from '@components/Docs';

### Modular Models

<DocumentationNotice />


Authorization is application-specific. In an organization with multiple teams building different applications or modules, each team should be able to define and evolve their authorization policies independently.

Modular models allows splitting your authorization model across multiple files and modules, improving upon some of the challenges that may be faced when maintaining an authorization model within a company, such as:

- A model can grow large and difficult to understand.
- As more teams begin to contribute to a model, the ownership boundaries may not be clear and code review processes might not scale.

With modular models, a single model can be split across multiple files in a project and organized in a way that makes sense for the project or teams collaborating on it. For example, modular models allows ownership for reviews to be expressed using a feature like [GitHub's](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/about-code-owners), [GitLab's](https://docs.gitlab.com/ee/user/project/codeowners/) or [Gitea's](https://docs.gitea.com/usage/code-owners) code owners.

#### Key Concepts

##### `fga.mod`

The `fga.mod` file is the project file for modular models. It specifies the schema version for the final combined model and lists the individual files that make up the modular model.

| Property | Description |
| -------- | -------- | 
| `schema` | The schema version to be used for the combined model |
| `contents` | The individual files that make up the modular model |

##### Modules


<ProductName format={ProductNameFormat.ShortForm}/> modules define the types and relations for a specific application module or service. 

Modules are declared using the `module` keyword in the DSL, and a module can be written across multiple files. A single file cannot have more than one module. 

##### Type Extensions

As teams implement features, they might find that core types they are dependent upon might not contain all the relations they need. However, it might not make sense for these relations to be owned by the owner of that type if they aren't needed across the system.

Modular models solves that problem by allowing individual types to be extended within other modules to to share those relations.

The following are requirements for type extension:

- The extended type must exist
- A single type can only be extended once per file
- The relations added must not already exist, or be part of another type extension

#### Example


The following example shows how an authorization model for a SaaS compny with a issue tracking and wiki software can implement modular models.

##### Core

If there is a core set of types owned by a team that manages the overall identity for the company, the following provides the basics: users, organizations and groups that can be used by each product area.


```dsl.openfga
module core

type user

type organization
  relations
    define member: [user]
    define admin: [user]

type group
  relations
    define member: [user]
```

##### Issue tracking

The issue tracking software separates out the project- and issue-related types into separate files. Below, we also extend the `organization` type to add a relation specific to the issue tracking feature: the ability to authorize who can create a project.

```dsl.openfga
module issue-tracker

extend type organization
  relations
    define can_create_project: admin

type project
  relations
    define organization: [organization]
    define viewer: member from organization
```

```dsl.openfga
module issue-tracker

type ticket
  relations
    define project: [project]
    define owner: [user]
```

##### Wiki

The wiki model is managed in one file until it grows. We can also extend the `organization` type again to add a relation tracking who can create a space.

```dsl.openfga
module wiki

extend type organization
  relations
    define can_create_space: admin


type space
  relations
    define organization: [organization]
    define can_view_pages: member from organization

type page
  relations
    define space: [space]
    define owner: [user]
```

##### `fga.mod`

To deploy this model, create the `fga.mod` manifest file, set a schema version, and list the individual module files that comprise the model.

```yaml
schema: '1.2'
contents:
  - core.fga
  - issue-tracker/projects.fga
  - issue-tracker/tickets.fga
  - wiki.fga
```

##### Putting it all together

With individual parts of the modular model in place, write the model to <ProductName format={ProductNameFormat.ShortForm}/> and run tests against it. Below is an example of what to run in the CLI:

```shell
fga model write --store-id=$FGA_STORE_ID --file fga.mod
```

This model can now be queried and have tuples written to it, just like a singular file authorization model.

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:anne',
      relation: 'admin',
      object: 'organization:acme',
    },
    {
      user: 'organization:acme',
      relation: 'organization',
      object: 'space:acme',
    },
    {
      user: 'organization:acme',
      relation: 'organization',
      object: 'project:acme',
    },
  ]}
  skipSetup={true}
  allowedLanguages={[
    SupportedLanguage.JS_SDK,
    SupportedLanguage.GO_SDK,
    SupportedLanguage.DOTNET_SDK,
    SupportedLanguage.PYTHON_SDK,
    SupportedLanguage.JAVA_SDK,
    SupportedLanguage.CLI,
    SupportedLanguage.CURL,
  ]}
/>

<CheckRequestViewer user={'user:anne'} relation={'can_create_space'} object={'organization:acme'} allowed={true} />

##### Viewing the model

When using the CLI to view the combined model DSL with `fga model get --store-id=$FGA_STORE_ID`, the DSL is annotated with comments defining the source module and file for types, relations and conditions.

For example, the `organization` type shows that the type is defined in the `core.fga` file as part of the `core` module, the `can_create_project` relation is defined in `issue-tracker/projects.fga` as part of the `issuer-tracker` module, and the `can_create_space` relation is defined in the `wiki.fga` file as part of the `wiki` module.

```dsl.openfga
type organization # module: core, file: core.fga
  relations
    define admin: [user]
    define member: [user] or admin
    define can_create_project: admin # extended by: module: issue-tracker, file: issue-tracker/projects.fga
    define can_create_space: admin # extended by: module: wiki, file: wiki.fga
```


<!-- End of openfga/openfga.dev/docs/content/modeling/modular-models.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/multiple-restrictions.mdx -->

---
sidebar_position: 9
slug: /modeling/multiple-restrictions
description: Modeling system that requires multiple authorizations before allowing users to perform actions on particular objects
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  RelationshipTuplesViewer,
} from '@components/Docs';

### Multiple Restrictions

<DocumentationNotice />

In this guide we are going to model system that requires multiple authorizations before allowing users to perform actions on particular objects using <ProductName format={ProductNameFormat.ProductLink}/>.
For example, _<ProductConcept section="what-is-a-user" linkName="users" />_ are allowed to delete a `document` if both of these conditions are met:

- they are a member of the organization that owns the document
- they have writer permissions on the document

In this way, we prevent other users from deleting such document.

<CardBox title="When to use" appearance="filled">

This is useful when:

- Limiting certain actions (such as deleting or reading sensitive document) to privileged users.
- Adding restrictions and requiring multiple authorization paths before granting access.

</CardBox>

#### Before You Start

In order to understand this guide correctly you must be familiar with some <ProductConcept /> and know how to develop the things that we will list below.

<details>
<summary>

You will start with the _<ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />_ below,
it represents a `document` _<ProductConcept section="what-is-a-type" linkName="type" />_ that can have users
**<ProductConcept section="what-is-a-relation" linkName="related" />** as `writer` and `organizations` related as `owner`.
Document's `can_write` relation is based on whether user is a writer to the document. The `organization` type can have users related as `member`.

Let us also assume that we have:

- A `document` called "planning" owned by the ABC `organization`.
- Becky is a member of the ABC `organization`.
- Carl is a member of the XYZ `organization`.
- Becky and Carl both have `writer` access to the "planning" `document`.

</summary>

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          owner: {
            this: {},
          },
          writer: {
            this: {},
          },
          can_write: {
            computedUserset: {
              object: '',
              relation: 'writer',
            },
          },
        },
        metadata: {
          relations: {
            owner: { directly_related_user_types: [{ type: 'organization' }] },
            writer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'organization',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

The current state of the system is represented by the following relationship tuples being in the system already:

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      _description: 'organization ABC is the owner of planning document',
      user: 'organization:ABC',
      relation: 'owner',
      object: 'document:planning',
    },
    {
      _description: 'Becky is a writer to the planning document',
      user: 'user:becky',
      relation: 'writer',
      object: 'document:planning',
    },
    {
      _description: 'Carl is a writer to the planning document',
      user: 'user:carl',
      relation: 'writer',
      object: 'document:planning',
    },
    {
      _description: 'Becky is a member of the organization ABC',
      user: 'user:becky',
      relation: 'member',
      object: 'organization:ABC',
    },
    {
      _description: 'Carl is a member of the organization XYZ',
      user: 'user:carl',
      relation: 'member',
      object: 'organization:XYZ',
    },
  ]}
/>

:::info
Note that we assign the organization, not the organization's members, as owner to the planning document.
:::

<hr />

In addition, you will need to know the following:

##### Modeling Parent-Child Objects

You need to know how to model access based on parent-child relationships, e.g.: folders and documents. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/./parent-child.mdx)

##### Modeling Roles And Permissions

You need to know how to model roles for users at the object level and model permissions for those roles. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/./roles-and-permissions.mdx)

##### <ProductName format={ProductNameFormat.ShortForm} /> Concepts

- A <ProductConcept section="what-is-a-type" linkName="Type" />: a class of objects that have similar characteristics
- A <ProductConcept section="what-is-a-user" linkName="User" />: an entity in the system that can be related to an object
- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: is a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system
- An <ProductConcept section="what-is-an-object" linkName="Object" />: represents an entity in the system. Users' relationships to it can be define through relationship tuples and the authorization model
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a grouping consisting of a user, a relation and an object stored in <ProductName format={ProductNameFormat.ShortForm} />
- [Intersection Operator](https://github.com/openfga/openfga.dev/blob/main/../configuration-language.mdx#the-intersection-operator): the intersection operator can be used to indicate a relationship exists if the user is in all the sets of users

</details>

#### Step By Step

With the above authorization model and relationship tuples, <ProductName format={ProductNameFormat.LongForm} /> will correctly respond with `{"allowed":true}` when *<ProductConcept section="what-is-a-check-request" linkName="check" />*is called to see if Carl and Becky can write this `document`.

We can verify that by issuing two check requests:

<CheckRequestViewer user={'user:becky'} relation={'can_write'} object={'document:planning'} allowed={true} />

<CheckRequestViewer user={'user:carl'} relation={'can_write'} object={'document:planning'} allowed={true} />

What we would like to do is offer a way so that a document can be written by Becky and Carl, but only writers who are also members of the organization that owns the document can remove it.

To do this, we need to:

1. [Add can_delete relation to only allow writers that are members of the ownership organization](#01-add-can_delete-relation-to-only-allow-writers-that-are-members-of-the-ownership-organization)
2. [Verify that our solutions work](#02-verify-that-our-solutions-work)

##### 01. Add can_delete Relation To Only Allow Writers That Are Members Of The Ownership Organization

The first step is to add the relation definition for `can_delete` so that it requires users to be both `writer` and `member` of the owner. This is accomplished via the keyword [`and`](https://github.com/openfga/openfga.dev/blob/main/../configuration-language.mdx#the-intersection-operator).

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          owner: {
            this: {},
          },
          writer: {
            this: {},
          },
          can_write: {
            computedUserset: {
              object: '',
              relation: 'writer',
            },
          },
          can_delete: {
            intersection: {
              child: [
                {
                  computedUserset: {
                    object: '',
                    relation: 'writer',
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'owner',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'member',
                    },
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            owner: { directly_related_user_types: [{ type: 'organization' }] },
            writer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'organization',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

##### 02. Verify That Our Solutions Work

To verify that our solutions work, we need to check that Becky can delete the planning document because she is a writer AND she is a member of organization:ABC that owns the planning document.

<CheckRequestViewer user={'user:becky'} relation={'can_delete'} object={'document:planning'} allowed={true} />

However, Carl cannot delete the planning document because although he is a writer, Carl is not a member of organization:ABC that owns the planning document.

<CheckRequestViewer user={'user:carl'} relation={'can_delete'} object={'document:planning'} allowed={false} />

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to model privileged access."
  relatedLinks={[
    {
      title: 'Modeling: User Groups',
      description: 'Learn about how to add group members.',
      link: './user-groups',
      id: './user-groups',
    },
    {
      title: 'Modeling: Blocklists',
      description: 'Learn about how to set block lists.',
      link: './blocklists',
      id: './blocklists',
    },
    {
      title: 'Modeling: Public Access',
      description: 'Learn about model public access.',
      link: './public-access',
      id: './public-access',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/multiple-restrictions.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/organization-context-authorization.mdx -->

---
sidebar_position: 9
slug: /modeling/organization-context-authorization
description: Modeling authorization through organization context
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  Playground,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  RelationshipTuplesViewer,
  UpdateProductNameInLinks,
  WriteRequestViewer,
} from '@components/Docs';

### Authorization Through Organization Context

<DocumentationNotice />

This section tackles cases where a user may have access to a particular resource through their presence in a particular organization, and they should have that access only when logged in within the context of that organization.

<CardBox title="When to use" appearance="filled">
Contextual Tuples should be used when modeling cases where a user's access to an object depends on the context of their request. For example:

- An employee’s ability to access a document when they are connected to the organization VPN or the api call is originating from an internal IP address.
- A support engineer is only able to access a user's account during office hours.
- If a user belongs to multiple organizations, they are only able to access a resource if they set a specific organization in their current context.

</CardBox>

#### Before You Start

To follow this guide, you should be familiar with some <ProductConcept />.

##### <ProductName format={ProductNameFormat.ShortForm}/> Concepts

- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: is a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system
- A <ProductConcept section="what-is-a-check-request" linkName="Check Request" />: is a call to the <ProductName format={ProductNameFormat.ShortForm}/> check endpoint that returns whether the user has a certain relationship with an object.
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a grouping consisting of a user, a relation and an object stored in <ProductName format={ProductNameFormat.ShortForm}/>
- A <ProductConcept section="what-are-contextual-tuples" linkName="Contextual Tuple" />: a tuple that can be added to a check request, and only exist within the context of that particular request.

You also need to be familiar with:

- **Modeling Object-to-Object Relationships**: You need to know how to create relationships between objects and how that might affect a user's relationships to those objects. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/./building-blocks/object-to-object-relationships.mdx)
- **Modeling Multiple Restrictions**: You need to know how to model requiring multiple authorizations before allowing users to perform certain actions. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/./multiple-restrictions.mdx)

<Playground />

##### Scenario

For the scope of this guide, we are going to consider the following scenario.

Consider you are building the authorization model for a multi-tenant project management system.

In this particular system:

- projects are owned and managed by companies
- users can be members of multiple companies
- project access is governed by the user's role in the organization that manages the project

In order for a user to access a project:

- The project needs to be managed by an organization the user is a member of
- A project is owned by a single organization
- A project can be shared with partner companies (that are able to view, edit but not perform admin actions, such as deletion, on the project)
- The user should have a role that grants access to the project
- The user should be logged in within the context of that organization

We will start with the following authorization model:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'organization',
        relations: {
          member: {
            this: {},
          },
          project_manager: {
            this: {},
          },
          project_editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
            project_manager: { directly_related_user_types: [{ type: 'user' }] },
            project_editor: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'project',
        relations: {
          owner: {
            this: {},
          },
          partner: {
            this: {},
          },
          manager: {
            tupleToUserset: {
              tupleset: {
                object: '',
                relation: 'owner',
              },
              computedUserset: {
                object: '',
                relation: 'project_manager',
              },
            },
          },
          editor: {
            union: {
              child: [
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'owner',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'project_editor',
                    },
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'partner',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'project_editor',
                    },
                  },
                },
                {
                  computedUserset: {
                    object: '',
                    relation: 'manager',
                  },
                },
              ],
            },
          },
          can_delete: {
            computedUserset: {
              object: '',
              relation: 'manager',
            },
          },
          can_edit: {
            computedUserset: {
              object: '',
              relation: 'editor',
            },
          },
          can_view: {
            computedUserset: {
              object: '',
              relation: 'editor',
            },
          },
        },
        metadata: {
          relations: {
            owner: { directly_related_user_types: [{ type: 'organization' }] },
            partner: { directly_related_user_types: [{ type: 'organization' }] },
          },
        },
      },
    ],
  }}
/>

<details>
<summary>

We are considering the case that:

- Anne has a project manager role at organizations A, B and C
- Beth has a project manager role at organization B
- Carl has a project manager role at organization C
- Project X is owned by organization A
- Project X is shared with organization B

</summary>

The above state translates to the following relationship tuples:

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'Anne has a `project manager` role at organization A',
      user: 'user:anne',
      relation: 'project_manager',
      object: 'organization:A',
    },
    {
      _description: 'Anne has a `project manager` role at organization B',
      user: 'user:anne',
      relation: 'project_manager',
      object: 'organization:B',
    },
    {
      _description: 'Anne has a `project manager` role at organization C',
      user: 'user:anne',
      relation: 'project_manager',
      object: 'organization:C',
    },
    {
      _description: 'Beth has a `project manager` role at organization B',
      user: 'user:anne',
      relation: 'project_manager',
      object: 'organization:B',
    },
    {
      _description: 'Carl has a `project manager` role at organization C',
      user: 'user:carl',
      relation: 'project_manager',
      object: 'organization:C',
    },
    {
      _description: 'Organization A owns Project X',
      user: 'organization:A',
      relation: 'owner',
      object: 'project:X',
    },
    {
      _description: 'Project X is shared with Organization B',
      user: 'organization:B',
      relation: 'partner',
      object: 'project:X',
    },
  ]}
  skipSetup={true}
/>

</details>

##### Requirements

- When logging in within the context of organization A, Anne should be able to view and delete project X.
- When logging in within the context of organization B, Anne should be able to view, but not delete, project X.
- When logging in within the context of organization C, Anne should not be able to view nor delete project X.
- When logging in within the context of organization B, Beth should be able to view, but not delete, project X.
- Carl should not be able to view nor delete project X.

#### Step By Step

In order to solve for the requirements above, we will break the problem down into three steps:

1. [Understand relationships without contextual tuples](#understand-relationships-without-contextual-data). For example, we need to ensure that Anne can view and delete "Project X".
2. [Take organization context into consideration](#take-organization-context-into-consideration). This includes [extending the authorization model](#extend-the-authorization-model) and a temporary step of [adding the required tuples to mark that Anne is in an approved context](#add-the-required-tuples-to-mark-that-anne-is-in-an-approved-context).
3. [Use contextual tuples for context related checks](#use-contextual-tuples-for-context-related-checks).

##### Understand Relationships Without Contextual Data

With the authorization model and relationship tuples shown above, <ProductName format={ProductNameFormat.ShortForm}/> has all the information needed to ensure that Anne can view and delete "Project X".

We can verify that using the following checks:

- Anne can view Project X
  <CheckRequestViewer user={'user:anne'} relation={'can_view'} object={'project:X'} allowed={true} skipSetup={true} />
- Anne can delete Project X
  <CheckRequestViewer user={'user:anne'} relation={'can_delete'} object={'project:X'} allowed={true} skipSetup={true} />

<details>
  <summary>More checks</summary>
  * Beth can view Project X
  <CheckRequestViewer user={'user:beth'} relation={'can_view'} object={'project:X'} allowed={true} skipSetup={true} />
  * Beth cannot delete Project X
  <CheckRequestViewer user={'user:beth'} relation={'can_delete'} object={'project:X'} allowed={false} skipSetup={true} />
  * Carl cannot view Project X
  <CheckRequestViewer user={'user:carl'} relation={'can_view'} object={'project:X'} allowed={false} skipSetup={true} />
  * Carl cannot delete Project X
  <CheckRequestViewer user={'user:carl'} relation={'can_delete'} object={'project:X'} allowed={false} skipSetup={true} />
</details>

Note that so far, we have not prevented Anne from viewing "Project X" even if Anne is viewing it from the context of Organization C.

##### Take Organization Context Into Consideration

###### Extend The Authorization Model

In order to add a restriction based on the current organization context, we will make use of <ProductName format={ProductNameFormat.ShortForm}/> configuration language's support for [intersection](https://github.com/openfga/openfga.dev/blob/main/../configuration-language.mdx#the-intersection-operator) to specify that a user has to both have access _and_ be in the correct context in order to be authorized.

We can do that by introducing some new relations and updating existing relation definitions:

1. On the "organization" type

- Add "user_in_context" relation to mark that a user's access is being evaluated within that particular context
- Update the "project_manager" relation to require that the user be in the correct context (by adding `and user_in_context` to the relation definition)
- Considering that <ProductName format={ProductNameFormat.ShortForm}/> does not yet support multiple logical operations within the same definition, we will split "project_editor" into two:
  - "base_project_editor" editor which will contain the original relation definition (`[user] or project_manager`)
  - "project_editor" which will require that a user has both the "base_project_editor" and the "user_in_context" relations

The "organization" type definition then becomes:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type: 'organization',
    relations: {
      member: {
        this: {},
      },
      project_manager: {
        intersection: {
          child: [
            {
              this: {},
            },
            {
              computedUserset: {
                object: '',
                relation: 'user_in_context',
              },
            },
          ],
        },
      },
      base_project_editor: {
        union: {
          child: [
            {
              this: {},
            },
            {
              computedUserset: {
                object: '',
                relation: 'project_manager',
              },
            },
          ],
        },
      },
      project_editor: {
        intersection: {
          child: [
            {
              computedUserset: {
                object: '',
                relation: 'base_project_editor',
              },
            },
            {
              computedUserset: {
                object: '',
                relation: 'user_in_context',
              },
            },
          ],
        },
      },
      user_in_context: {
        this: {},
      },
    },
    metadata: {
      relations: {
        member: { directly_related_user_types: [{ type: 'user' }] },
        project_manager: { directly_related_user_types: [{ type: 'user' }] },
        base_project_editor: { directly_related_user_types: [{ type: 'user' }] },
        user_in_context: { directly_related_user_types: [{ type: 'user' }] },
      },
    },
  }}
  skipVersion={true}
/>

2. On the "project" type

- Nothing will need to be done, as it will inherit the updated "project_manager" and "project_editor" relation definitions from "organization"

###### Add The Required Tuples To Mark That Anne Is In An Approved Context

Now that we have updated our authorization model to take the current user's organization context into consideration, you will notice that Anne has lost access because nothing indicates that Anne is authorizing from the context of an organization. You can verify that by issuing the following check:

<CheckRequestViewer user={'user:anne'} relation={'can_view'} object={'project:X'} allowed={false} skipSetup={true} />

In order for Anne to be authorized, a tuple indicating Anne's current organization context will need to be present:

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'Anne is authorizing from the context of organization:A',
      user: 'user:anne',
      relation: 'user_in_context',
      object: 'organization:A',
    },
  ]}
/>

We can verify this by running a check request

<CheckRequestViewer user={'user:anne'} relation={'can_view'} object={'project:X'} allowed={true} skipSetup={true} />

##### Use Contextual Tuples For Context Related Checks

Now that we know we can authorize based on present state, we have a different problem to solve. We are storing the tuples in the state in order for <ProductName format={ProductNameFormat.ShortForm}/> to evaluate them, which fails in certain use-cases where Anne can be connected to two different contexts in different browser windows at the same time, as each has a different context at the same time, so if they are written to the state, which will <ProductName format={ProductNameFormat.ShortForm}/> use to compute Anne's access to the project?

For Check calls, <ProductName format={ProductNameFormat.ShortForm}/> has a concept called "<ProductConcept section="what-are-contextual-tuples" linkName="Contextual Tuples" />". Contextual Tuples are tuples that do **not** exist in the system state and are not written beforehand to <ProductName format={ProductNameFormat.ShortForm}/>. They are tuples that are sent alongside the Check request and will be treated as _if_ they already exist in the state for the context of that particular Check call. That means that Anne can be using two different sessions, each within a different organization context, and <ProductName format={ProductNameFormat.ShortForm}/> will correctly respond to each one with the correct authorization decision.

First, we will undo the [temporary step](#add-the-required-tuples-to-mark-that-anne-is-in-an-approved-context) and remove the stored tuples for which Anne has a `user_in_context` relation with `organization:A`.

<WriteRequestViewer
  deleteRelationshipTuples={[
    {
      _description: 'Delete stored tuples where Anne is authorizing from the context of organization:A',
      user: 'user:anne',
      relation: 'user_in_context',
      object: 'organization:A',
    },
  ]}
/>

Next, when Anne is connecting from the context of organization A, <ProductName format={ProductNameFormat.ShortForm}/> will return `{"allowed":true}`:

<CheckRequestViewer
  user={'user:anne'}
  relation={'can_view'}
  object={'project:X'}
  allowed={true}
  skipSetup={true}
  contextualTuples={[
    {
      _description: 'Anne is authorizing from the context of organization:A',
      user: 'user:anne',
      relation: 'user_in_context',
      object: 'organization:A',
    },
  ]}
/>

When Anne is connecting from the context of organization C, <ProductName format={ProductNameFormat.ShortForm}/> will return `{"allowed":false}`:

<CheckRequestViewer
  user={'user:anne'}
  relation={'can_view'}
  object={'project:X'}
  allowed={false}
  skipSetup={true}
  contextualTuples={[
    {
      _description: 'Anne is authorizing from the context of organization:A',
      user: 'user:anne',
      relation: 'user_in_context',
      object: 'organization:C',
    },
  ]}
/>

Using this, you can check that the following requirements are satisfied:

| User | Organization Context | Action | Allowed |
| ---- | -------------------- | ------ | ------- |
| Anne | Organization A       | View   | Yes     |
| Anne | Organization B       | View   | Yes     |
| Anne | Organization C       | View   | Yes     |
| Anne | Organization A       | Delete | Yes     |
| Anne | Organization B       | Delete | No      |
| Anne | Organization C       | Delete | No      |
| Beth | Organization B       | View   | Yes     |
| Beth | Organization B       | Delete | No      |
| Carl | Organization C       | View   | No      |
| Carl | Organization C       | Delete | No      |

#### Summary

<details>
<summary>
  Final version of the Authorization Model and Relationship tuples
</summary>
<AuthzModelSnippetViewer configuration={{
    schema_version: '1.1',
  "type_definitions": [
    {
      "type": "user",
    },
    {
      "type": "organization",
      "relations": {
        "member": {
          "this": {}
        },
        "project_manager": {
          "intersection": {
            "child": [
              {
                "this": {}
              },
              {
                "computedUserset": {
                  "object": "",
                  "relation": "user_in_context"
                }
              }
            ]
          }
        },
        "base_project_editor": {
          "union": {
            "child": [
              {
                "this": {}
              },
              {
                "computedUserset": {
                  "object": "",
                  "relation": "project_manager"
                }
              }
            ]
          }
        },
        "project_editor": {
          "intersection": {
            "child": [
              {
                "computedUserset": {
                  "object": "",
                  "relation": "base_project_editor"
                }
              },
              {
                "computedUserset": {
                  "object": "",
                  "relation": "user_in_context"
                }
              }
            ]
          }
        },
        "user_in_context": {
          "this": {}
        }
      },
      metadata: {
        relations: {
          member: { directly_related_user_types: [{type: 'user'}] },
          project_manager: { directly_related_user_types: [{type: 'user'}] },
          base_project_editor: { directly_related_user_types: [{type: 'user'}] },
          user_in_context: { directly_related_user_types: [{type: 'user'}] },
        },
      },
    },
    {
      "type": "project",
      "relations": {
        "owner": {
          "this": {}
        },
        "partner": {
          "this": {}
        },
        "manager": {
          "tupleToUserset": {
            "tupleset": {
              "object": "",
              "relation": "owner"
            },
            "computedUserset": {
              "object": "",
              "relation": "project_manager"
            }
          }
        },
        "editor": {
          "union": {
            "child": [
              {
                "computedUserset": {
                  "object": "",
                  "relation": "manager"
                }
              },
              {
                "tupleToUserset": {
                  "tupleset": {
                    "object": "",
                    "relation": "owner"
                  },
                  "computedUserset": {
                    "object": "",
                    "relation": "project_editor"
                  }
                }
              },
              {
                "tupleToUserset": {
                  "tupleset": {
                    "object": "",
                    "relation": "partner"
                  },
                  "computedUserset": {
                    "object": "",
                    "relation": "project_editor"
                  }
                }
              }
            ]
          }
        },
        "can_delete": {
          "computedUserset": {
            "object": "",
            "relation": "manager"
          }
        },
        "can_edit": {
          "computedUserset": {
            "object": "",
            "relation": "editor"
          }
        },
        "can_view": {
          "computedUserset": {
            "object": "",
            "relation": "editor"
          }
        }
      },
      metadata: {
        relations: {
          owner: { directly_related_user_types: [{type: 'organization'}] },
          partner: { directly_related_user_types: [{type: 'organization'}] },
        },
      },
    }
  ]
}} />

<WriteRequestViewer relationshipTuples={[
  {
    "_description": "Anne has a `project manager` role at organization A",
    "user": "user:anne",
    "relation": "project_manager",
    "object": "organization:A"
  }, {
    "_description": "Anne has a `project manager` role at organization B",
    "user": "user:anne",
    "relation": "project_manager",
    "object": "organization:B"
  }, {
    "_description": "Anne has a `project manager` role at organization C",
    "user": "user:anne",
    "relation": "project_manager",
    "object": "organization:C"
  }, {
    "_description": "Beth has a `project manager` role at organization B",
    "user": "user:beth",
    "relation": "project_manager",
    "object": "organization:B"
  }, {
    "_description": "Carl has a `project manager` role at organization C",
    "user": "user:carl",
    "relation": "project_manager",
    "object": "organization:C"
  }, {
    "_description": "Organization A owns Project X",
    "user": "organization:A",
    "relation": "owner",
    "object": "project:X"
  }, {
    "_description": "Project X is shared with Organization B",
    "user": "organization:B",
    "relation": "partner",
    "object": "project:X"
  },
]} skipSetup={true} />
</details>

:::caution Warning
Contextual tuples:

- Are not persisted in the store.
- Are only supported on the <UpdateProductNameInLinks link="/api/service#Relationship%20Queries/Check" name="Check API endpoint" /> and <UpdateProductNameInLinks link="/api/service#Relationship%20Queries/ListObjects" name="ListObjects API endpoint" />. They are not supported on read, expand and other endpoints.
- If you are using the <UpdateProductNameInLinks link="/api/service#Relationship%20Tuples/ReadChanges" name="Read Changes API endpoint" /> to build a permission aware search index, note that it will not be trivial to take contextual tuples into account.

:::

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how user groups can be used."
  relatedLinks={[
    {
      title: 'Modeling with Multiple Restrictions',
      description:
        'Learn how to model requiring multiple relationships before users are authorized to perform certain actions.',
      link: './multiple-restrictions',
      id: './multiple-restrictions.mdx',
    },
    {
      title: 'Contextual and Time-Based Authorization',
      description: 'Learn how to authorize access that depends on dynamic or contextual criteria.',
      link: './contextual-time-based-authorization',
      id: './contextual-time-based-authorization.mdx',
    },
    {
      title: '{ProductName} Check API',
      description: 'Details on the Check API in the {ProductName} reference guide.',
      link: '/api/service#Relationship%20Queries/Check',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/organization-context-authorization.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/overview.mdx -->

---
id: overview
title: 'Modeling Guides'
slug: /modeling
sidebar_position: 0
---

import { DocumentationNotice, IntroCard, CardGrid } from '@components/Docs';

<DocumentationNotice />

This section has guides, concepts and examples that help you define an authorization model. 

You can also check out the [Modeling Guide](https://www.youtube.com/watch?v=5Lwy9aHXXHE&list=PLUR5l-oTFZqWaDdhEOVt_IfPOIbKo1Ypt) on YouTube or the [Samples Repository](https://github.com/openfga/sample-stores).

<IntroCard
  title="When to use"
  description="The content in this section is useful:"
  listItems={[
    `If you are starting with {ProductName} and want to learn how to represent your organization's/system's authorization needs.`,
    `If you are working on iterating on an authorization model you previously defined.`,
  ]}
/>

### Content

<CardGrid
  top={[
    {
      title: 'Getting Started',
      description: 'How to create an authorization model for your system starting from the requirements.',
      to: 'modeling/getting-started',
    },
  ]}
  middle={[
    {
      title: 'Direct Access',
      description: 'Learn the basics of modeling authorization and granting access to users.',
      to: 'modeling/direct-access',
    },
    {
      title: 'User Groups',
      description: 'Learn to model user group membership, and to grant access to all members of a group.',
      to: 'modeling/user-groups',
    },
    {
      title: 'Roles and Permissions',
      description: 'Learn to model roles for users at the object level and model permissions for those roles.',
      to: 'modeling/roles-and-permissions',
    },
    {
      title: 'Parent-Child objects',
      description: 'Learn to model access based on parent-child relationships, e.g.: folders and documents.',
      to: 'modeling/parent-child',
    },
    {
      title: 'Block Lists',
      description: 'Learn to model denying access if users are part of list of blocked users.',
      to: 'modeling/blocklists',
    },
    {
      title: 'Public Access',
      description: 'Learn to model giving everyone specific access to an object, e.g.: everyone can read.',
      to: 'modeling/public-access',
    },
    {
      title: 'Multiple Restrictions',
      description: 'Learn to model requiring multiple privileges before granting access.',
      to: 'modeling/multiple-restrictions',
    },
    {
      title: 'Custom Roles',
      description: 'Learn to model custom roles that are created by users.',
      to: 'modeling/custom-roles',
    },
    {
      title: 'Conditions',
      description: 'Learn to model requiring dynamic attributes.',
      to: 'modeling/conditions',
    },
    {
      title: 'Contextual and Time-Based Authorization',
      description:
        'Learn to model and authorize when IP Address, time, and other dynamic and contextual restrictions are involved.',
      to: 'modeling/contextual-time-based-authorization',
    },
    {
      title: 'Authorization Through Organization Context',
      description: 'Learn to model and authorize when a user belongs to multiple organizations.',
      to: 'modeling/organization-context-authorization',
    },
  ]}
  bottom={[
    {
      title: 'Building Blocks',
      description: 'Learn the underlying concepts/building blocks that can be used to build any model.',
      to: 'modeling/building-blocks',
    },
    {
      title: 'Advanced Use-Cases',
      description: 'Explore advanced use cases and patterns for authorization modeling with OpenFGA.',
      to: 'modeling/advanced',
    },
    {
      title: 'Migrating',
      description: 'Learn to migrate relations and models in a production environment.',
      to: 'modeling/migrating',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/overview.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/parent-child.mdx -->

---
sidebar_position: 7
slug: /modeling/parent-child
description: Indicate relationships between objects, and how users' relationships to one object can affect their relationship with another
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  Playground,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  WriteRequestViewer,
} from '@components/Docs';

### Parent-Child Objects

<DocumentationNotice />

In <ProductName format={ProductNameFormat.ShortForm}/>, a user's <ProductConcept section="what-is-a-relationship" linkName="relationship" /> with an <ProductConcept section="what-is-an-object" linkName="object" /> can affect their relationship with another object. For example, an `editor` of a `folder` can also be an `editor` of all `documents` that `folder` is a `parent` of.

<CardBox title="When to use" appearance="filled">

Object-to-object relationships can combine with a configured authorization model to indicate that a user's relationship with one object may influence the user's relationship with another object. They can also eliminate the need to modify relationships between objects using [user groups](https://github.com/openfga/openfga.dev/blob/main/./user-groups.mdx#03-assign-the-team-members-a-relation-to-an-object).

The follow are examples of simple object-to-object relationships:

- `managers` of an `employee` have access to `approve` requests the `employee` has made
- users who have a repository admin role (`repo_admin`) in an organization automatically have `admin` access to all repositories in that organization
- users who are `subscribed` to a `plan` get access to all the `features` in that `plan`

</CardBox>

#### Before you start

Familiarize yourself with basic <ProductConcept />:

<details>
<summary>

Assume that you have the following <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />.<br />
You have two types:

- `folder` that users can be related to as an `editor`
- `document` that users can be related to as an `editor`

</summary>

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'folder',
        relations: {
          editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            editor: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'document',
        relations: {
          editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            editor: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

<hr />

In addition:

##### Direct access

Creating an authorization model and a relationship tuple can grant a user access to an object. To learn more, [read about Direct Access](https://github.com/openfga/openfga.dev/blob/main/./direct-access.mdx)

##### <ProductName format={ProductNameFormat.ShortForm}/> concepts

- A <ProductConcept section="what-is-a-type" linkName="Type" />: a class of objects that have similar characteristics
- A <ProductConcept section="what-is-a-user" linkName="User" />: an entity in the system that can be related to an object
- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system
- An <ProductConcept section="what-is-an-object" linkName="Object" />: represents an entity in the system. Users' relationships to it can be define through relationship tuples and the authorization model
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a group stored in <ProductName format={ProductNameFormat.ShortForm}/> that consists of a user, a relation, and an object 
- [Union Operator](https://github.com/openfga/openfga.dev/blob/main/../configuration-language.mdx#the-union-operator): can be used to indicate that the user has multiple ways of being related to an object

</details>

<Playground />

#### Step by step

The following walkthrough models (a) folders that contain documents and (b) that a user who has editor access to a given folder has editor access to all documents in that folder.

For `editors` of a `folder` to be `editors` of a containing `document`, you must:

1. Update the authorization model to allow a `parent` relationship between `folder` and `document`
2. Update the `editor` relation in the `document` type definition to support cascading from `folder`

The following three steps indicate and verify that `bob` is an `editor` of `document:meeting_notes.doc` because `bob` is an `editor` of `folder:notes`:

3. Create a new _relationship tuple_ to indicate that **bob** is a `editor` of **folder:notes**
4. Create a new _relationship tuple_ to indicate that **folder:notes** is a `parent` of **document:meeting_notes.doc**
5. Check to see if **bob** is an `editor` of **document:meeting_notes.doc**

##### 01. Update the Athorization Model to allow a parent relationship between folder and document

As documented in [Modeling Concepts: Object to Object Relationships](https://github.com/openfga/openfga.dev/blob/main/./building-blocks/object-to-object-relationships.mdx), the following update to the authorization model allows a `parent` relation between a `folder` and a `document`:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'folder',
        relations: {
          editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            editor: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'document',
        relations: {
          // A folder can be a parent of a document
          parent: {
            this: {},
          },
          editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            parent: { directly_related_user_types: [{ type: 'folder' }] },
            editor: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

:::info

The `document` type now has a `parent` relation, indicating that other objects can be `parent`s of `document`s

:::

##### 02. Update the editor relation in the document type definition to support cascading from folder

To allow cascading relations between `folder` and `document`, update the authorization model:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'folder',
        relations: {
          editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            editor: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'document',
        relations: {
          parent: {
            this: {},
          },
          editor: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      relation: 'parent',
                    },
                    computedUserset: {
                      relation: 'editor',
                    },
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            parent: { directly_related_user_types: [{ type: 'folder' }] },
            editor: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

:::info

`editor` of a `document` can be the following:

1. users that are directly assigned as editors
2. users that are related to any `parent` of this document as `editor` (editors of the parent)

:::

After making these changes, anyone related to a `folder` that is a `parent` of a `document` as an `editor` is also an `editor` of that `document`.

##### 03. Create a new relationship tuple to indicate that `bob` is an `editor` of `folder:notes`

To leverage the new cascading relation, create a relationship tuple stating that `bob` is an `editor` of `folder:notes`

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:bob',
      relation: 'editor',
      object: 'folder:notes',
    },
  ]}
/>

:::caution
**Note:** Use unique ids for each object and user within your application domain when creating relationship tuples for <ProductName format={ProductNameFormat.LongForm}/>. We use first names and simple ids below as an easy-to-follow example.
:::

##### 04. Create a new relationship tuple to indicate that `folder:notes` is a `parent` of `document:meeting_notes.doc`

Now that `bob` is an `editor` of `folder:notes`, we need to indicate that **folder:notes** is a `parent` of `document:meeting_notes.doc`

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'the notes folder is a parent of the meeting notes document',
      user: 'folder:notes',
      relation: 'parent',
      object: 'document:meeting_notes.doc',
    },
  ]}
/>

##### 05. Check if `bob` is an `editor` of `document:meeting_notes.doc`

After changing the authorization model and adding two new relationship tuples, verify that your configuration is correct by running the following check: **is bob an editor of document:meeting_notes.doc**.

<CheckRequestViewer user={'user:bob'} relation={'editor'} object={'document:meeting_notes.doc'} allowed={true} />

> Note: There are no other relationship tuples in the store that dictate a direct relation between `bob` and `document:meeting_notes.doc`. The check succeeds because of the cascading relation.

The chain of resolution is:

- `bob` is an `editor` of `folder:notes`
- `folder:notes` is a `parent` of `document:meeting_notes.doc`
- `editors` of any `parent` `folder` of `document:meeting_notes.doc` are also `editors` of the `document`
- therefore `bob` is an `editor` of `document:meeting_notes.doc`

:::caution
When searching tuples that are related to the object (the word after `from`, also called the tupleset), <ProductName format={ProductNameFormat.LongForm}/> will not do any evaluation and only considers concrete objects (of the form `<object_type>:<object_id>`) that were directly assigned. <ProductName format={ProductNameFormat.LongForm}/> will throw an error if it encounters any rewrites, a `*`, a type bound public access (`<object_type>:*`), or a userset (`<object_type>:<object_id>#<relation>`).

For more information on this topic, see [Referencing Relations on Related Objects](https://github.com/openfga/openfga.dev/blob/main/../configuration-language.mdx#referencing-relations-on-related-objects).
:::

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to model for parent and child objects."
  relatedLinks={[
    {
      title: 'Modeling Concepts: Object to Object Relationships',
      description: 'Learn about how to model object to object relationships in {ProductName}.',
      link: './building-blocks/object-to-object-relationships',
      id: './building-blocks/object-to-object-relationships',
    },
    {
      title: 'Modeling Google Drive',
      description:
        'See how to make folders parents of documents, and to make editors on the parent folders editors on documents inside them..',
      link: './advanced/gdrive#01-individual-permissions',
      id: './advanced/gdrive.mdx#01-individual-permissions',
    },
    {
      title: 'Modeling GitHub',
      description: 'See how to grant users access to all repositories owned by an organization.',
      link: './advanced/github#01-permissions-for-individuals-in-an-org',
      id: './advanced/github.mdx#01-permissions-for-individuals-in-an-org',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/parent-child.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/public-access.mdx -->

---
sidebar_position: 3
slug: /modeling/public-access
description: Granting public access to an object
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  Playground,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  RelationshipTuplesViewer,
  WriteRequestViewer,
} from '@components/Docs';

### Public Access

<DocumentationNotice />

In this guide you will learn how to grant public access to an <ProductConcept section="what-is-an-object" linkName="object" />, such as a certain document, using <ProductConcept section="what-is-type-bound-public-access" linkName="type bound public access" />.

<CardBox title="When to use" appearance="filled">

Public access allows your application to grant every user in the system access to an object. You would add a relationship tuple with type-bound public access when:

- sharing a `document` publicly to indicate that everyone can `view` it
- a public `poll` is created to indicate that anyone can `vote` on it
- a blog `post` is published and anyone should be able to `read` it
- a `video` is made public for anyone to `watch`

</CardBox>

#### Before You Start

In order to understand this guide correctly you must be familiar with some <ProductConcept /> and know how to develop the things that we will list below.

<details>
<summary>

Assume that you have the following <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />.<br />
You have a <ProductConcept section="what-is-a-type" linkName="type" /> called `document` that can have a `view` relation.

</summary>

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          view: {
            this: {},
          },
        },
        metadata: {
          relations: {
            view: { directly_related_user_types: [{ type: 'user' }, {type: 'user', wildcard:{} }] },
          },
        },
      },
    ],
  }}
/>

<hr />

In addition, you will need to know the following:

##### Direct Access

You need to know how to create an authorization model and create a relationship tuple to grant a user access to an object. [Learn more →](https://github.com/openfga/openfga.dev/blob/main/./direct-access.mdx)

##### <ProductName format={ProductNameFormat.ShortForm}/> Concepts

- A <ProductConcept section="what-is-a-type" linkName="Type" />: a class of objects that have similar characteristics
- A <ProductConcept section="what-is-a-user" linkName="User" />: an entity in the system that can be related to an object
- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: is a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system
- An <ProductConcept section="what-is-an-object" linkName="Object" />: represents an entity in the system. Users' relationships to it can be define through relationship tuples and the authorization model
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a grouping consisting of a user, a relation and an object stored in <ProductName format={ProductNameFormat.ShortForm}/>
- A <ProductConcept section="what-is-type-bound-public-access" linkName="Type Bound Public Access" />: is a special <ProductName format={ProductNameFormat.ShortForm}/> concept (represented by `<type>:*`) can be used in relationship tuples to represent every object of that type

</details>

:::caution
Make sure to use unique ids for each object and user within your application domain when creating relationship tuples for <ProductName format={ProductNameFormat.LongForm}/>. We are using first names and simple ids to just illustrate an easy-to-follow example.
:::

<Playground />

#### Step By Step

In previous guides, we have shown how to indicate that objects are related to users or objects. In some cases, you might want to indicate that everyone is related to an object (for example when sharing a document publicly).

##### 01. Create A Relationship Tuple

To do this we need to create a relationship tuple using the <ProductConcept section="what-is-type-bound-public-access" linkName="type bound public access" />. The type bound public access syntax is used to indicate that all users of a particular type have a relation to a specific object.

Let us create a relationship tuple that states: **any user can view document:company-psa.doc**

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'user:* denotes every object of type user',
      user: 'user:*',
      relation: 'view',
      object: 'document:company-psa.doc',
    },
  ]}
/>

:::caution Wildcard syntax usage

Please note that type-bound public access is not a wildcard or a regex expression.

**You cannot use the `<type>:*` syntax in the tuple's object field.**

The following syntax is invalid:

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      _description: 'It is invalid to use this syntax in the object field. The below relationship tuple is invalid and does not mean that Bob can view all documents.',
      user: 'user:bob',
      relation: 'view',
      object: 'document:*',
    },
  ]}
/>

:::

:::caution Wildcard syntax usage

**You cannot use `<type>:*` as part of a userset in the tuple's user field.**

The following syntax is invalid:

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      _description: 'It is invalid to use this syntax as part of a userset. The below relationship tuple is invalid and does not mean that members of any org can view the company-psa document.',
      user: 'org:*#member',
      relation: 'view',
      object: 'document:company-psa.doc',
    },
  ]}
/>

:::

##### 02. Check That The Relationship Exists

Once the above _relationship tuple_ is added, we can <ProductConcept section="what-is-a-check-request" linkName="check" /> if **bob** cab `view` `document`:**company-psa.doc**. <ProductName format={ProductNameFormat.ShortForm}/> will return `{ "allowed": true }` even though no relationship tuple linking **bob** to the document was added. That is because the relationship tuple with `user:*` as the user made it so every object of type user (such as `user:bob`) can `view` the document, making it public.

<CheckRequestViewer user={'user:bob'} relation={'view'} object={'document:company-psa.doc'} allowed={true} />

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to model with {ProductName}."
  relatedLinks={[
    {
      title: 'Modeling: Getting Started',
      description: 'Learn about how to get started with modeling.',
      link: './getting-started',
      id: './getting-started',
    },
    {
      title: 'Configuration Language',
      description: 'Learn about {ProductName} Configuration Language.',
      link: '../configuration-language',
      id: '../configuration-language',
    },
    {
      title: 'Modeling Blocklists',
      description: 'Learn about model block lists.',
      link: './blocklists',
      id: './blocklists',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/public-access.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/roles-and-permissions.mdx -->

---
sidebar_position: 5
slug: /modeling/roles-and-permissions
description: Modeling basic roles and permissions
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  Playground,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  WriteRequestViewer,
} from '@components/Docs';

### Roles and Permissions

<DocumentationNotice />

Roles and permissions can be modeled within <ProductName format={ProductNameFormat.ProductLink}/> using an <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" /> and <ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuples" />.

- **Roles** are assigned to <ProductConcept section="what-is-a-user" linkName="users" /> or a group of users. Any user can have more than one role, like `editor` or `owner`.
- **Permissions** allow users to access certain <ProductConcept section="what-is-an-object" linkName="objects" /> based on their specific roles, like `device_renamer` or `channel_archiver`.

For example, the role `viewer` of a `trip` can have permissions to view bookings, while the role `owners` can have permissions to add or view trip bookings.

<CardBox title="When to use a Roles and Permissions model" appearance="filled">

Role and permissions models in <ProductName format={ProductNameFormat.ShortForm}/> can both directly assign roles to users and assign permissions through relations users receive downstream from other relations. For example, you can:

- Grant someone an `admin` role that can `edit` and `read` a `document`
- Grant someone a `security_guard` role that can `live_video_viewer` on a `device`
- Grant someone a `viewer` role that can `view_products` on a `shop`

Implementing a Roles and Permissions model allows existing roles to have finer-grained permissions, allowing your application to check whether a user has access to a certain object without having to explicitly check that specific users role. In addition, you can add new roles/permissions or consolidate roles without affecting your application behavior. For example, if your app's checks are for the fine permissions, like `check('bob', 'booking_adder', 'trip:Europe')` instead of `check('bob', 'owner', 'trip:Europe')`, and you later decide `owners` can no longer add bookings to a `trip`, you can remove the relation within the `trip` type with no code changes in your application and all permissions will automatically honor the change.

</CardBox>

#### Before you start

Familiarize yourself with the basics of <ProductConcept />.

<details>
<summary>

Assume that you have the following <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" /> and a <ProductConcept section="what-is-a-type" linkName="type" /> called `trip` that users can be related to as `viewer` and/or an `owner`.

</summary>

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'trip',
        relations: {
          owner: {
            this: {},
          },
          viewer: {
            this: {},
          },
        },
        metadata: {
          relations: {
            owner: { directly_related_user_types: [{ type: 'user' }] },
            viewer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

<hr />

In addition, you need to know the following:

##### Direct Access

Creating an authorization model and a relationship tuple can grant a user access to an object. To learn more, [read about Direct Access](https://github.com/openfga/openfga.dev/blob/main/./direct-access.mdx)

##### <ProductName format={ProductNameFormat.ShortForm}/> Concepts

- A <ProductConcept section="what-is-a-type" linkName="Type" />: a class of objects that have similar characteristics
- A <ProductConcept section="what-is-a-user" linkName="User" />: an entity in the system that can be related to an object
- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system
- An <ProductConcept section="what-is-an-object" linkName="Object" />: represents an entity in the system. Users' relationships to it can be define through relationship tuples and the authorization model
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a group stored in <ProductName format={ProductNameFormat.ShortForm}/> that consists of a user, a relation, and an object 
- A <ProductConcept section="what-is-a-relationship" linkName="Relationship" />: <ProductName format={ProductNameFormat.ShortForm}/> will be called to check if there is a relationship between a user and an object, indicating that the access is allowed
- [Union Operator](https://github.com/openfga/openfga.dev/blob/main/../configuration-language.mdx#the-union-operator): can be used to indicate that the user has multiple ways of being related to an object
- [Direct Relationship Type Restrictions](https://github.com/openfga/openfga.dev/blob/main/../configuration-language.mdx#direct-relationship-type-restrictions): can be used to indicate direct relationships between users and objects
- A <ProductConcept section="what-is-a-check-request" linkName="Check API Request" />: used to check for relationships between users and objects

</details>

<Playground />

#### Step by step

The Roles and Permissions example below is a trip booking system that has `owners` and/or `viewers`, both of which can have more granular permissions like adding bookings to a trip or viewing a trip's bookings.

To represent this in an <ProductName format={ProductNameFormat.ProductLink}/> environment, you need to:

1. Understand how roles are related to direct relations for the trip booking system
2. Add implied relations to the existing authorization model to define permissions for bookings
3. <ProductConcept section="what-is-a-check-request" linkName="Check" /> user roles and their permissions based on relationship
   tuples for direct and implied relations

##### 01. Understand how roles work within the trip booking system

Roles are relations that are directly assigned to users. Below, the stated roles that a given user can be assigned are `owner` and `viewer`.

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'trip',
        relations: {
          // Users can have role: 'owner'
          owner: {
            this: {},
          },
          // Users can have role: 'viewer'
          viewer: {
            this: {},
          },
        },
        metadata: {
          relations: {
            owner: { directly_related_user_types: [{ type: 'user' }] },
            viewer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

##### 02. Add permissions for bookings

Permissions are relations that users get through other relations. To avoid adding a [direct relationship type restriction](https://github.com/openfga/openfga.dev/blob/main/../configuration-language.mdx#direct-relationship-type-restrictions) to the relation in the authorization model while representing permissions, they instead define the relation via other relations in the model, which indicates that it is a permission granted to and implied from a different relation.

To add permissions related to bookings, add new relations to the `trip` object type denoting the various actions a user can take on `trips`, like view, edit, delete, or rename.

To allow `viewers` of a `trip` to have permissions to view bookings and `owners` to have permissions to add/view bookings, you modify the type:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'trip',
        relations: {
          // User Roles: `owner` and `viewer`
          owner: {
            this: {},
          },
          viewer: {
            this: {},
          },
          // Permission `booking_adder`
          booking_adder: {
            // Users with role: `owner` can add bookings
            computedUserset: {
              relation: 'owner',
            },
          },
          // Permission `booking_viewer`
          booking_viewer: {
            union: {
              child: [
                {
                  // Users with role: `viewer` can view bookings
                  computedUserset: {
                    relation: 'viewer',
                  },
                },
                {
                  // Users with role: `owner` can view bookings
                  computedUserset: {
                    relation: 'owner',
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            owner: { directly_related_user_types: [{ type: 'user' }] },
            viewer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

> Note: both `booking_viewer` and `booking_adder` don't have [direct relationship type restrictions](https://github.com/openfga/openfga.dev/blob/main/../configuration-language.mdx#direct-relationship-type-restrictions), which ensures that the relation can only be assigned through the role and not directly.

##### 03. Check user roles and their permissions

Your type definitions reflects the roles and permissions on how bookings can be viewed/added, so you can create <ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuples" /> to assign roles to users, then <ProductConcept section="what-is-a-check-request" linkName="check" /> if users have the proper permissions.

Create two relationship tuples:

1. give `bob` the role of `viewer` on `trip` called Europe.
2. give `alice` the role of `owner` on `trip` called Europe.

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: 'Add bob as viewer on trip:Europe',
      user: 'user:bob',
      relation: 'viewer',
      object: 'trip:Europe',
    },
    {
      _description: 'Add alice as owner on trip:Europe',
      user: 'user:alice',
      relation: 'owner',
      object: 'trip:Europe',
    },
  ]}
/>

Now check: is `bob` allowed to view bookings on trip Europe?

<CheckRequestViewer user={'user:bob'} relation={'booking_viewer'} object={'trip:Europe'} allowed={true} />

`bob` is a `booking_viewer` because of the following chain of resolution:

1. `bob` is a `viewer` on `trip`: Europe
2. Any user related to the _object_ `trip:`Europe as `viewer` is also related as a `booking_viewer` (i.e `usersRelatedToObjectAs: viewer`)
3. Therefore, all `viewers` on a given `trip` are `booking_viewers`

To confirm that `bob` is not allowed to add bookings on trip Europe, run the following check:

<CheckRequestViewer user={'user:bob'} relation={'booking_adder'} object={'trip:Europe'} allowed={false} />

You also check: is alice allowed to view and add bookings on trip Europe?

<CheckRequestViewer user={'user:alice'} relation={'booking_viewer'} object={'trip:Europe'} allowed={true} />
<CheckRequestViewer user={'user:alice'} relation={'booking_adder'} object={'trip:Europe'} allowed={true} />

`alice` is a `booking_viewer` and `booking_adder` because of the following chain of resolution:

1. `alice` is a `owner` on `trip`: Europe
2. Any user related to the _object_ `trip:`Europe as `owner` is also related as a `booking_viewer`
3. Any user related to the _object_ `trip:`Europe as `owner` is also related as a `booking_adder`
4. Therefore, all `owners` on a given `trip` are `booking_viewers` and `booking_adders` on that trip

:::caution
Use unique ids for each object and user within your application domain when creating relationship tuples for <ProductName format={ProductNameFormat.LongForm}/>. This example first names and simple ids as an easy-to-follow example.
:::

#### Related sections

<RelatedSection
  description="See following sections for more on how to model for roles and permissions."
  relatedLinks={[
    {
      title: 'Modeling Concepts: Concentric Relationships',
      description: 'Learn about how to represent a concentric relationships in {ProductName}.',
      link: './building-blocks/concentric-relationships',
      id: './building-blocks/concentric-relationships',
    },
    {
      title: 'Modeling Google Drive',
      description: 'See how to indicate that editors are commenters and viewers in Google Drive.',
      link: './advanced/gdrive#01-individual-permissions',
      id: './advanced/gdrive.mdx#01-individual-permissions',
    },
    {
      title: 'Modeling GitHub',
      description: 'See how to indicate that repository admins are writers and readers in GitHub.',
      link: './advanced/github#01-permissions-for-individuals-in-an-org',
      id: './advanced/github.mdx#01-permissions-for-individuals-in-an-org',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/roles-and-permissions.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/store-file-format.mdx -->

---
sidebar_position: 7
slug: /modeling/store-file-format
description: Store File Format (.fga.yaml)
---

import {
  DocumentationNotice,
  ProductName,
  ProductNameFormat,
  RelatedSection,
} from '@components/Docs';

### Store File Format

<DocumentationNotice />

:::note
This documentation is mirrored from the [OpenFGA CLI repository](https://github.com/openfga/cli/blob/main/docs/STORE_FILE.md). For the most up-to-date version, please refer to the CLI documentation.
:::

The store file is a YAML configuration file (`.fga.yaml`) that defines a complete <ProductName format={ProductNameFormat.ShortForm}/> store setup, including the authorization model, relationship tuples, and test cases. This file format enables easy management, testing, and deployment of <ProductName format={ProductNameFormat.ShortForm}/> configurations.

#### File Structure

The store file uses YAML syntax and supports the following top-level properties:

```yaml
name: "Store Name"                    # Required: Name of the store
model_file: "./model.fga"             # Path to authorization model file
model: |                              # OR inline model definition
  model
    schema 1.1
  type user
  # ... more model definitions

tuple_file: "./tuples.yaml"           # Path to tuples file  
tuples:                               # OR inline tuples
  - user: user:anne
    relation: viewer
    object: document:1

tests:                                # Test definitions
  - name: "test-name"
    description: "Test description"   # Optional
    tuple_file: "./test-tuples.yaml"  # Test-specific tuples file
    tuples:                           # OR inline test tuples
      - user: user:bob
        relation: editor
        object: document:2
    check:                            # Authorization checks
      - user: user:anne
        object: document:1
        context:                      # Optional context for ABAC
          timestamp: "2023-05-03T21:25:23+00:00"
        assertions:
          viewer: true
          editor: false
    list_objects:                     # List objects tests
      - user: user:anne
        type: document
        context:                      # Optional context
          timestamp: "2023-05-03T21:25:23+00:00"
        assertions:
          viewer:
            - document:1
            - document:2
    list_users:                       # List users tests
      - object: document:1
        user_filter:
          - type: user
        context:                      # Optional context
          timestamp: "2023-05-03T21:25:23+00:00"
        assertions:
          viewer:
            users:
              - user:anne
              - user:bob
```

#### Core Components

##### 1. Store Metadata

- **`name`** (required): The display name for the store
- This name is used when creating a new store via import

##### 2. Authorization Model

You can specify the authorization model in two ways:

###### Option A: External File Reference
```yaml
model_file: "./path/to/model.fga"
```

###### Option B: Inline Model Definition
```yaml
model: |
  model
    schema 1.1
  
  type user
  
  type document
    relations
      define viewer: [user]
      define editor: [user] and viewer
```

The model defines the authorization schema including:
- Types (user, document, folder, etc.)
- Relations (viewer, editor, owner, etc.)  
- Authorization rules and conditions

##### 3. Relationship Tuples

Tuples define the actual relationships between users and objects. You can specify them in two ways:

###### Option A: External File Reference
```yaml
tuple_file: "./path/to/tuples.yaml"
```

###### Option B: Inline Tuple Definition
```yaml
tuples:
  - user: user:anne
    relation: viewer  
    object: document:1
  - user: user:bob
    relation: editor
    object: document:1
    condition:                        # Optional: for conditional relationships
      name: valid_ip
      context:
        ip_address: "192.168.1.100"
```

**Supported tuple file formats:**
- YAML (`.yaml`, `.yml`)
- JSON (`.json`)
- CSV (`.csv`)

##### 4. Tests

The `tests` array contains test cases to validate your authorization model and tuples.

###### Test Structure
Each test can include:
- **`name`** (required): Test identifier
- **`description`** (optional): Human-readable test description
- **`tuple_file`** or **`tuples`**: Test-specific relationship tuples (appended to global tuples)
- **`check`**: Authorization check tests
- **`list_objects`**: List objects API tests  
- **`list_users`**: List users API tests

###### Check Tests
Validate whether a user has specific relations to an object:

```yaml
check:
  - user: user:anne
    object: document:1
    context:                          # Optional: for ABAC conditions
      current_time: "2023-05-03T21:25:23+00:00"
      user_ip: "192.168.0.1"
    assertions:
      viewer: true                    # Expected result
      editor: false
      owner: false
```

###### List Objects Tests
Validate which objects a user can access:

```yaml
list_objects:
  - user: user:anne
    type: document                    # Object type to query
    context:                          # Optional context
      current_time: "2023-05-03T21:25:23+00:00"
    assertions:
      viewer:                         # Objects user can view
        - document:1
        - document:2  
      editor:                         # Objects user can edit
        - document:1
```

###### List Users Tests
Validate which users have access to an object:

```yaml
list_users:
  - object: document:1
    user_filter:                      # Filter by user types
      - type: user
      - type: team
    context:                          # Optional context
      current_time: "2023-05-03T21:25:23+00:00"
    assertions:
      viewer:
        users:
          - user:anne
          - user:bob
```

#### Context Support (ABAC)

The store file supports Attribute-Based Access Control (ABAC) through contextual information:

```yaml
# In tuples - for conditional relationships
tuples:
  - user: user:anne
    relation: viewer
    object: document:1
    condition:
      name: non_expired_grant
      context:
        grant_timestamp: "2023-05-03T21:25:20+00:00"
        grant_duration: "10m"

# In tests - for contextual evaluations
tests:
  - name: "time-based-access"
    check:
      - user: user:anne
        object: document:1
        context:
          current_timestamp: "2023-05-03T21:25:23+00:00"
        assertions:
          viewer: true
```

#### File Composition

The store file supports flexible composition:

##### Global + Test-Specific Data
- **Global tuples**: Applied to all tests
- **Test-specific tuples**: Appended to global tuples for individual tests
- Both `tuple_file` and `tuples` can be used together

##### Mixed Inline and File References
```yaml
name: "Mixed Example"
model_file: "./model.fga"            # Model from file
tuples:                              # Inline global tuples
  - user: user:admin
    relation: owner
    object: system:main
tests:
  - name: "test-1"
    tuple_file: "./test1-tuples.yaml" # Additional tuples from file
    check:
      - user: user:admin
        object: system:main
        assertions:
          owner: true
```

#### CLI Commands Using Store Files

##### Store Import
Import a complete store configuration:
```bash
fga store import --file store.fga.yaml
```

##### Model Testing  
Run tests against an authorization model:
```bash
fga model test --tests store.fga.yaml
```

##### Store Export
Export store configuration to file:
```bash
fga store export --store-id 01H0H015178Y2V4CX10C2KGHF4 > exported-store.fga.yaml
```

#### Examples

##### Basic Store File
```yaml
name: "Document Management"
model_file: "./authorization-model.fga"
tuple_file: "./relationships.yaml"
tests:
  - name: "basic-permissions"
    check:
      - user: user:alice
        object: document:readme
        assertions:
          viewer: true
          editor: false
```

##### Advanced Store with ABAC
```yaml
name: "Time-Based Access"
model: |
  model
    schema 1.1
  
  type user
  type document
    relations
      define viewer: [user with non_expired_grant]

  condition non_expired_grant(current_time: timestamp, grant_time: timestamp, duration: duration) {
    current_time < grant_time + duration
  }

tuples:
  - user: user:bob
    relation: viewer
    object: document:secret
    condition:
      name: non_expired_grant
      context:
        grant_time: "2023-05-03T21:25:20+00:00"
        duration: "1h"

tests:
  - name: "time-expiry-test"
    check:
      - user: user:bob
        object: document:secret
        context:
          current_time: "2023-05-03T21:30:00+00:00"  # Within 1 hour
        assertions:
          viewer: true
      - user: user:bob
        object: document:secret  
        context:
          current_time: "2023-05-03T22:30:00+00:00"  # After 1 hour
        assertions:
          viewer: false
```

##### Multi-Test Store File
```yaml
name: "Comprehensive Testing"
model_file: "./model.fga"
tuple_file: "./base-tuples.yaml"

tests:
  - name: "admin-permissions"
    tuples:
      - user: user:admin
        relation: owner
        object: system:config
    check:
      - user: user:admin
        object: system:config
        assertions:
          owner: true
          viewer: true
    list_objects:
      - user: user:admin
        type: system
        assertions:
          owner:
            - system:config

  - name: "user-permissions" 
    tuple_file: "./user-test-tuples.yaml"
    check:
      - user: user:john
        object: document:public
        assertions:
          viewer: true
          editor: false
    list_users:
      - object: document:public
        user_filter:
          - type: user
        assertions:
          viewer:
            users:
              - user:john
              - user:jane
```

#### Best Practices

1. **Use descriptive names**: Make store and test names clear and meaningful
2. **Organize with external files**: For complex models, use separate `.fga` files for models and `.yaml` files for tuples
3. **Comprehensive testing**: Include check, list_objects, and list_users tests to validate all API behaviors
4. **Context testing**: When using ABAC, test both positive and negative cases with different context values
5. **Modular tuples**: Use both global and test-specific tuples to avoid repetition
6. **Version control**: Store files work well with Git for tracking authorization changes over time

#### File Extensions

- **Store files**: `.fga.yaml` (recommended) or `.yaml`
- **Model files**: `.fga` (recommended) or `.mod`
- **Tuple files**: `.yaml`, `.json`, or `.csv`

The `.fga.yaml` extension is the conventional naming pattern that makes store files easily identifiable and helps with tooling integration.

<RelatedSection
  description="Learn more about testing models"
  relatedLinks={[
    {
      title: 'Testing Models',
      description: 'Learn how to test your authorization models',
      link: './testing',
      type: 'tutorial',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/store-file-format.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/testing-models.mdx -->

---
sidebar_position: 6
slug: /modeling/testing
description: Testing Models
---

import {
  DocumentationNotice,
  ProductName,
  ProductNameFormat,
  RelatedSection,
} from '@components/Docs';

### Testing Models

<DocumentationNotice />

Every <ProductName format={ProductNameFormat.ShortForm}/> model should be tested before deployment to ensure your authorization model is correctly designed.

The `.fga.yaml` contains tests for <ProductName format={ProductNameFormat.ShortForm}/> authorization models. If you are using Visual Studio Code as your IDE, install the [OpenFGA extension](https://marketplace.visualstudio.com/items?itemName=openfga.openfga-vscode) to enable syntax coloring and validation.

For complete details on the `.fga.yaml` store file format, see [Store File Format](https://github.com/openfga/openfga.dev/blob/main/./store-file-format.mdx).


#### Define the model and tuples

`.fga.yaml` files have the following top level items:

| Object  | Description |
| -------- | -------- | 
| `name` (optional)   | A descriptive name for the test file   | 
| `model` or `model_file`   | An <ProductName format={ProductNameFormat.ShortForm}/> model or a reference to an external model file in `fga`, `json` or `mod` format  | 
|`tuples` or `tuple_file` or multiple `tuple_files` (optional) | A set of tuples or a reference to an external tuple file in `json`, `yaml` or `csv` format. These are considered for all tests. |
|`tests` | A set of tests that verify the return values of <ProductName format={ProductNameFormat.ShortForm}/> API calls |

The example below defines a model and tuples:

```yaml
name: Model Tests # optional

# model_file: ./model.fga # you can specify an external .fga file, or include it inline
model: |
  model
    schema 1.1

  type user

  type organization
     relations
       define member : [user]
       define admin : [user with non_expired_grant]

   condition non_expired_grant(current_time: timestamp, grant_time: timestamp, grant_duration: duration) {
     current_time < grant_time + grant_duration
  }

# You can provide relationship tuples in one of the following ways:
# - As a single external file using 'tuple_file'
# - As multiple external files using 'tuple_files'
# - Inline directly using 'tuples'
#
# Examples:
# tuple_file: ./tuples.yaml           # Single external file
# tuple_files:                        # Multiple external files
#   - ./tuples_2.yaml
#   - ./tuples_3.yaml
tuples:                                # Inline tuple definitions go here


   # Anne is a member of the Acme organization
  - user: user:anne
    relation: member
    object: organization:acme

  # Peter has the admin role from February 2nd 2024 0AM to 1AM
  - user: user:peter
    relation: admin
    object: organization:acme
    condition: 
      name: non_expired_grant
      context: 
        grant_time : "2024-02-01T00:00:00Z"
        grant_duration : 1h

```
#### Write tests

Always write tests to verify that the calls your application will make return the results you expect. A good test covers scenarios that verify every relation.

Tests have the following structure:

| Object  | Description |
| -------- | -------- | 
|`name` (optional) | A descriptive name for the test, like “Organization Membership”  | 
|`tuple_file` or `tuple_files` or `tuples` | A set of tuples that are only considered for the test | 
|`check` | A set of tests for Check calls, each with a user/object and a set of assertions |
|`list_objects` | A set of tests for ListObjects calls, each one with a user/type and a set of assertions for any number of relations|
|`list_users` | A set of tests for ListUsers calls, each one with an object and user filter and a set of assertions for the users for any number of relations |

#### Write Check tests

Check tests verify the results of the [check API](https://github.com/openfga/openfga.dev/blob/main/../getting-started/perform-check.mdx) calls to validate access requirements for a user. Each check verification has the following structure:

| Object  | Description |
| -------- | -------- | 
|`user` | The user type and user id you are checking for access | 
|`object` | The object type and object id related to the user | 
|`context` | A set of tests for contextual parameters used to evaluate [conditions](https://github.com/openfga/openfga.dev/blob/main/./conditions.mdx)|
|`assertions` | A list of `relation:expected-result` pairs |
|`<relation>: <true or false>` | The name of the relation you want to verify and the expected result |

The following example adds multiple check verifications in every test:

```yaml
tests:
  - name: Test
    check:
      - user: user:anne
        object: organization:acme
        assertions:
          member: true
          admin: false

      - user: user:peter
        object: organization:acme
        context: 
          current_time : "2024-02-01T00:10:00Z"
        assertions:
          member: false
          admin: true
```

#### Write List Objects tests

A good test covers scenarios that specify every relation for every object type that your application will need to call the [list-objects API](https://github.com/openfga/openfga.dev/blob/main/../getting-started/perform-list-objects.mdx) for.

The following verifies the expected results using the `list_objects` option in <ProductName format={ProductNameFormat.ShortForm}/> tests:

```yaml
    list_objects:
      - user: user:anne
        type: organization
        assertions:
            member: 
                - organization:acme
            admin: []
              
      - user: user:peter
        type: organization
        context: 
          current_time : "2024-02-01T00:10:00Z"

        assertions:
            member: []
            admin: 
                - organization:acme

```
The example above checks that `user:anne` has access to the `organization:acme` as a member and is not an admin of any organization. It also checks that `user:peter`, given the current time is February 1st 2024, 0:10 AM, is not related to any organization as a member, but is related to `organization:acme` as an admin.

#### Write List Users tests

List users tests verify the results of the [list-users API](https://github.com/openfga/openfga.dev/blob/main/../getting-started/perform-list-users.mdx) to validate the users who or do not have access to an object

Each list users verification has the following structure:

| Object  | Description |
| -------- | -------- | 
|`object` | The object to list users for |
|`user_filter` | Specifies the type or userset to filter with, this must only contain one entry |
|`user_filter.type` | The specific type of results to return with response |
|`user_filter.relation` | The specific relation of results to return with response. Specify to return usersets (optional) |
|`context` | A set of tests for contextual parameters used to evaluate [conditions](https://github.com/openfga/openfga.dev/blob/main/./conditions.mdx)|
|`assertions` | A list of assertions to make |
|`<relation>` | The name of the relation you want to verify |
|`<relation>.users` | The users who should have the stated relation to the object |

In order to simplify test writing, the following syntax is supported for the various object types included in `users` from the API response:

* `<type>:<id>` to represent a userset that is a user
* `<type>:<id>#<relation>` to represent a userset that is a relation on a type
* `<type>:*` to represent a userset that is a type bound public access for a type

The following is an example of using the `list_users` option in <ProductName format={ProductNameFormat.ShortForm}/> tests:

```yaml
    list_users:
      - object: organization:acme
        user_filter:
          - type: user
        context: 
          current_time : "2024-02-02T00:10:00Z"
        assertions:
            member:
              users:
                - user:anne
            admin: 
              users: []

```
The example above checks that the `organization:acme`, given the current time is February 2nd 2024, it has 'user:anne' as a `member`, nobody as an `admin`. If we tried with current time being February 1st 2024, then `user:peter` would be listed as an `admin`

#### Testing with Modular Models

If you are using [Modular Models](https://github.com/openfga/openfga.dev/blob/main/./modular-models.mdx), you need to use the `fga.mod` as the `model_file`. 

You can define tests for each model in separate `.fga.yaml` files, all of which should reference the common `fga.mod` model. Shared relationship tuples can be placed in a separate file and included using the `tuple_file` option. If needed, you can split tuples across multiple shared files and include them with the `tuple_files` option. Additionally, each `.fga.yaml` file can include module-specific tuples inline.

#### Running tests

Tests are run using the `model test` CLI command. For instructions on installing the OpenFGA CLI, visit the [OpenFGA CLI Github repository](https://github.com/openfga/cli).

```shell
fga model test --tests <filename>.fga.yaml
```

When all tests pass, a summary with the number of tests passed is displayed. When a test fails, a line for every test is displayed.

```shell
$ fga model test --tests docs.fga.yaml
# Test Summary #
Tests 2/2 passing
Checks 4/4 passing
ListObjects 4/4 passing

$ fga model test --tests docs.fga.yaml
(FAILING) : ListUsers(1/2 passing)
ⅹ ListUsers(object={Type:organization Id:acme},relation=member,user_filter={Type:user Relation:<nil>}, context:&map[current_time:2024-02-02T00:10:00Z]): expected={Users:[user:ann]}, got={Users:[user:anne]}
---
# Test Summary #
Tests 1/2 passing
Checks 4/4 passing
ListObjects 4/4 passing
```

#### Running tests using GitHub Actions

Use the [OpenFGA Model Testing Action](https://github.com/marketplace/actions/openfga-model-testing-action) to run tests from CI/CD flows in GitHub.

Set the path to the `.fga.yaml` file as the `store-file-path` parameter when configuring the action:

```yaml
name: Test Action

on:
  workflow_dispatch:
  pull_request:
    branches:
      - main

jobs:
  test:
    name: Run test
    runs-on: ubuntu-latest
    steps:
      - name: Checkout Project
        uses: actions/checkout@v4
      - name: Run Test
        uses: openfga/action-openfga-test@v0.1.0
        with:
          store-file-path: ./example/model.fga.yaml

```

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to learn how to write tests."
  relatedLinks={[
    {
      title: 'Use the FGA CLI ',
      description: 'Learn how to use the FGA CLI.',
      link: '../getting-started/cli',
      id:  '../getting-started/cli.mdx',
    },
    {
      title: 'Super Admin Example ',
      description: 'Define a model and tests for modeling a super-admin role.',
      link: 'https://github.com/openfga/sample-stores/blob/main/stores/superadmin/store.fga.yaml'
    }, 
    {
      title: 'Banking Example ',
      description: 'Define a model and tests for banking application.',
      link: 'https://github.com/openfga/sample-stores/blob/main/stores/banking/store.fga.yaml'
    },
    {
      title: 'Entitlements Example ',
      description: 'Define a model and tests for B2B application entitlements.',
      link: 'https://github.com/openfga/sample-stores/blob/main/stores/advanced-entitlements/store.fga.yaml'
    }
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/testing-models.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/token-claims-contextual-tuples.mdx -->

---
sidebar_position: 9
slug: /modeling/token-claims-contextual-tuples
description: Using identity token claims to define contextual relations
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  Playground,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  RelationshipTuplesViewer,
  UpdateProductNameInLinks,
  WriteRequestViewer,
} from '@components/Docs';

### Use Token Claims As Contextual Tuples

<DocumentationNotice />

Contextual Tuples allow authorization checks that depend on dynamic or contextual relationships that have not been written to the <ProductName format={ProductNameFormat.ShortForm}/> store, enabling some Attribute Based Access Control (ABAC) use cases. 

To enable more ABAC use-cases that rely on specific attributes and conditions, you can also use <ProductName format={ProductNameFormat.ShortForm}/>`s [conditions](https://github.com/openfga/openfga.dev/blob/main/./conditions.mdx).

#### Before You Start

To follow this guide, familiarize yourself with the following <ProductConcept />:

- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: is a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system.
- A <ProductConcept section="what-is-a-check-request" linkName="Check Request" />: is a call to the <ProductName format={ProductNameFormat.ShortForm}/> check endpoint that returns whether the user has a certain relationship with an object.
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a grouping consisting of a user, a relation and an object stored in <ProductName format={ProductNameFormat.ShortForm}/>

#### User Directories, Identity Tokens, And Relationships

User directories store user information that's accessed when making authorization decisions, like the group the user belongs to, their roles, or their department. The natural way to use those relationships in a Relationship-Based Access Control system like <ProductName format={ProductNameFormat.ShortForm}/> is to create tuples for each relation. However, implementing a synchronization mechanism to keep the user directory data up to date with tuples in the store can be challenging.  

When applications implement authentication using an OIDC authorization service, they receive an ID Token or an Access token, with certain claims that can be customized based on the application's needs. Instead of writing tuples to the <ProductName format={ProductNameFormat.ShortForm}/>, you can use the content of the token in Contextual Tuples to make authorization checks, understanding that, if those relationships change while the token has not expired, users will still get access to the resources the content of the token entitled them to.

#### Example

In this example, the application uses the following authorization model, in which documents can be viewed by members of a group:

<AuthzModelSnippetViewer
  configuration={{
  "schema_version":"1.1",
  "type_definitions": [
    {
      "type":"user"
    },
    {
      "metadata": {
        "relations": {
          "member": {
            "directly_related_user_types": [
              {
                "type":"user"
              }
            ]
          }
        }
      },
      "relations": {
        "member": {
          "this": {}
        }
      },
      "type":"group"
    },
    {
      "metadata": {
        "relations": {
          "viewer": {
            "directly_related_user_types": [
              {
                "relation":"member",
                "type":"group"
              }
            ]
          }
        }
      },
      "relations": {
        "viewer": {
          "this": {}
        }
      },
      "type":"document"
    }
  ]
}}
/>

When a group is added as a viewer of a document, the application writes tuples like those below:

<WriteRequestViewer relationshipTuples={[
  {
    "_description": "Members of the marketing group can view the product-launch document",
    "user": "group:marketing#member",
    "relation": "viewer",
    "object": "document:product-launch"
  },
  {
  "_description": "Members of the everyone group can view the welcome document",
  "user": "group:everyone#member",
  "relation": "viewer",
  "object": "document:welcome"
}
]} />

Let's assume that the Access Token the application receives has a list of the groups the user belongs to:

```json
{
  "iss": "https://id.company.com",
  "sub": "6b0b14af-59dc-4ff3-a46f-ad351f428726",
  "name": "John Doe",
  "iat": 1516239022,
  "exp": 1516239022,
  "azp" : "yz54KAoW1KGFAUU982CEUqZgxGIdrpgg",
  "groups": ["marketing", "everyone"]
}
```

When making a authorization check, the application uses the `groups` claim in the token and adds contextual tuple for each group, indicating that the user is a member of that group:

<CheckRequestViewer
  user={'user:6b0b14af-59dc-4ff3-a46f-ad351f428726'}
  relation={'viewer'}
  object={'document:product-launch'}
  allowed={true}
  contextualTuples={[
    {
      _description: 'user 6b0b14af-59dc-4ff3-a46f-ad351f428726 is a member of the marketing group',
      user: 'user:6b0b14af-59dc-4ff3-a46f-ad351f428726',
      relation: 'member',
      object: 'group:marketing',
    },
    {
      _description: 'user 6b0b14af-59dc-4ff3-a46f-ad351f428726 is a member of the everyone group',
      user: 'user:6b0b14af-59dc-4ff3-a46f-ad351f428726',
      relation: 'member',
      object: 'group:everyone',
    },
  ]}
/>

The authorization check returns `allowed = true`, as there's a stored tuple saying that members of the `marketing` group are viewers of the `product-launch` document, and there's a contextual tuple indicating that the user is a member of the `marketing` group.

:::caution Warning
Contextual tuples:
- Do not persist in the store.

- Are only supported on the <UpdateProductNameInLinks link="/api/service#Relationship%20Queries/Check" name="Check API endpoint" />, <UpdateProductNameInLinks link="/api/service#Relationship%20Queries/ListObjects" name="ListObjects API endpoint" /> and <UpdateProductNameInLinks link="/api/service#Relationship%20Queries/ListUsers" name="ListUsers API endpoint" />. They are not supported on read, expand, or other endpoints.

- If you use the <UpdateProductNameInLinks link="/api/service#Relationship%20Tuples/ReadChanges" name="Read Changes API endpoint" /> to build a permission aware search index, it may be difficult to account for contextual tuples.
:::

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how user contextual tuples can be used."
  relatedLinks={[
    {
      title: 'Contextual and Time-Based Authorization',
      description: 'Learn how to authorize access that depends on dynamic or contextual criteria.',
      link: './contextual-time-based-authorization',
      id: './contextual-time-based-authorization.mdx',
    },
    {
      title: 'Authorization Through Organization Context',
      description: 'Learn to model and authorize when a user belongs to multiple organizations.',
      link: './organization-context-authorization',
      id: './organization-context-authorization.mdx',
    },
    {
      title: 'Conditions',
      description: 'Learn to model requiring dynamic attributes.',
      link: './conditions',
      id: './conditions.mdx',
    },
    {
      title: '{ProductName} API',
      description: 'Details on the Check API in the {ProductName} reference guide.',
      link: '/api/service#Relationship%20Queries/Check',
    }
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/token-claims-contextual-tuples.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/modeling/user-groups.mdx -->

---
sidebar_position: 6
slug: /modeling/user-groups
description: Adding users to groups and granting group members access to an object
---

import {
  AuthzModelSnippetViewer,
  CardBox,
  CheckRequestViewer,
  DocumentationNotice,
  Playground,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  WriteRequestViewer,
} from '@components/Docs';

### User Groups

<DocumentationNotice />

To add users to groups and grant groups access to an <ProductConcept section="what-is-an-object" linkName="object" /> using <ProductName format={ProductNameFormat.ProductLink}/>.

<CardBox title="When to use" appearance="filled">

Relationship tuples can specify that an entire group has a relation to an object, which is helpful when you want to encompass a set of users with the same relation to an object. For example:

- Grant `viewer` access to a group of `engineers` in `roadmap.doc`
- Create a `block_list` of `members` who can't access a `document`
- Sharing a `document` with a `team`
- Granting `viewer` access to a `photo` to `followers` only
- Making a `file` viewable for all `users` within an `organization`
- Restricting access from or to `users` in a certain `locale`

</CardBox>

#### Before you start

Familiarize yourself with the <ProductConcept />.

<details>
<summary>

Assume you have the following <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />.<br />: you have an _<ProductConcept section="what-is-an-object" linkName="object" />_ called `document` that users can relate to as an `editor`.

</summary>

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            editor: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

<hr />

In addition, you will need to know the following:

##### Direct Access

You need to know how to create an authorization model and a relationship tuple to grant a user access to an object. To learn more, see [direct access](https://github.com/openfga/openfga.dev/blob/main/./direct-access.mdx).

##### <ProductName format={ProductNameFormat.ShortForm}/> Concepts

- A <ProductConcept section="what-is-a-type" linkName="Type" />: a class of objects that have similar characteristics.
- A <ProductConcept section="what-is-a-user" linkName="User" />: an entity in the system that can be related to an object.
- A <ProductConcept section="what-is-a-relation" linkName="Relation" />: a string defined in the type definition of an authorization model that defines the possibility of a relationship between an object of the same type as the type definition and a user in the system.
- An <ProductConcept section="what-is-an-object" linkName="Object" />: represents an entity in the system. Users' relationships to it can be defined with relationship tuples and the authorization model.
- A <ProductConcept section="what-is-a-relationship-tuple" linkName="Relationship Tuple" />: a grouping consisting of a user, a relation and an object stored in <ProductName format={ProductNameFormat.ShortForm}/>.

</details>

<Playground />

#### Step By Step

There are possible use cases where a group of users have a certain role on or permission to an object. For example, `members` of a certain `team` could have an `editor` relation to a certain `document`.

To represent this in <ProductName format={ProductNameFormat.ShortForm}/>:

<!-- We disable the check for these links because markdown-link-check doesn't support reading the docusaurus syntax to define the link -->
<!-- markdown-link-check-disable --> 
1. Introduce the concept of a `team` to the authorization model. [→](#step-1)
2. Add users as `members` to the `team`. [→](#step-2)
3. Assign the `team` members a relation to an object. [→](#step-3)
4. Check an individual member's access to the object. [→](#step-4)
<!-- markdown-link-check-enable -->

##### 01. Introduce the concept of a team to the authorization model {#step-1}

First, define the _<ProductConcept section="what-is-an-object" linkName="object" />_ `team` in your authorization model. In this use case, a `team` can have `member`s, so you make the following changes to the authorization model:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'document',
        relations: {
          editor: {
            this: {},
          },
        },
        metadata: {
          relations: {
            editor: { directly_related_user_types: [{ type: 'team', relation: 'member' }] },
          },
        },
      },
      {
        type: 'team',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

##### 02. Add users as members to the team {#step-2}

You can now assign _<ProductConcept section="what-is-a-user" linkName="users" />_ as `member`s of `team`s. Create a new _<ProductConcept section="what-is-a-relationship-tuple" linkName="relationship tuple" />_ that states `user:alice` is a member of `team:writers`.

<WriteRequestViewer
  relationshipTuples={[
    {
      user: 'user:alice',
      relation: 'member',
      object: 'team:writers',
    },
  ]}
/>

##### 03. Assign the team members a relation to an object {#step-3}

To represent groups, use the `type:object_id#relation` format, which represents the set of users related to the `type:object_id` as a certain relation. For example, `team:writers#members` represents the set of users related to the `team:writers` object as `member`s.

In order to assign `member`s of a `team` a relation to a `document`, create the following relationship tuple stating that members of `team:writers` are editors of `document:meeting_notes.doc`.

<WriteRequestViewer
  relationshipTuples={[
    {
      _description: "Set of users related to 'team:writers' as 'member'",
      user: 'team:writers#member',
      relation: 'editor',
      object: 'document:meeting_notes.doc',
    },
  ]}
/>

##### 04. Check an individual member's access to an object {#step-4}

Now that you have:

- a relationship tuple indicating that `alice` is a `member` of `team:writers`
- a relationship tuple indicating that `members` of `team:writers` are editors of `document:meeting_notes.doc`

The \*<ProductConcept section="what-is-a-check-request" linkName="check" />\ `is alice an editor of document:meeting_notes.doc` returns the following:

<CheckRequestViewer user={'user:alice'} relation={'editor'} object={'document:meeting_notes.doc'} allowed={true} />

The chain of resolution is:

- `alice` is `member` of `team:writers`
- `member`s of `team:writers` are `editor`s of `document:meeting_notes`  
- therefore, `alice` is `editor` of `document:meeting_notes`

:::caution

**Note:** When creating relationship tuples for <ProductName format={ProductNameFormat.ShortForm}/>,  use unique ids for each object and user in your application domain. This example uses first names and simple ids as a suggested example.

:::

#### Related Sections

<RelatedSection
  description="Check the following sections for more information on user groups."
  relatedLinks={[
    {
      title: 'Managing Group Membership',
      description: 'Learn how to add and remove users from groups',
      link: '../interacting/managing-group-membership',
      id: '../interacting/managing-group-membership.mdx',
    },
    {
      title: 'Modeling Google Drive',
      description: 'See how User Groups can be used to share documents within a domain in the Google Drive use-case.',
      link: './advanced/gdrive#02-organization-permissions',
      id: './advanced/gdrive.mdx#02-organization-permissions',
    },
    {
      title: 'Modeling GitHub',
      description: 'Granting teams permissions to a repo in the GitHub use-case.',
      link: './advanced/github#02-permissions-for-teams-in-an-org',
      id: './advanced/github.mdx#02-permissions-for-teams-in-an-org',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/modeling/user-groups.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/authorization-concepts.mdx -->

---
title: Authorization Concepts
description: Introduction to Authorization 
sidebar_position: 1
slug: /authorization-concepts
---

import { ProductName, ProductNameFormat, RelatedSection } from '@components/Docs';

### Authorization Concepts 

#### Authentication and Authorization

[Authentication](https://en.wikipedia.org/wiki/Authentication) ensures a user's identity. [Authorization](https://en.wikipedia.org/wiki/Authorization) determines if a user can perform a certain action on a particular resource.

For example, when you log in to Google, Authentication is the process of verifying that your username and password are correct. Authorization is the process of ensuring that you can access a given Google service or feature.

#### What is Fine-Grained Authorization?

Fine-Grained Authorization (FGA) implies the ability to grant specific users permission to perform certain actions in specific resources.

Well-designed FGA systems allow you to manage permissions for millions of objects and users. These permissions can change rapidly as a system continually adds objects and updates access permissions for its users. 

A notable example of FGA is Google Drive: access can be granted either to documents or to folders, as well as to individual users or users as a group, and access rights regularly change as new documents are created and shared with specific users or groups.

#### What is Role-Based Access Control?

In [Role-Based Access Control](https://en.wikipedia.org/wiki/Role-based_access_control) (RBAC), permissions are assigned to users based on their role in a system. For example, a user needs the `editor` role to edit content.

RBAC systems enable you to define users, groups, roles, and permissions, then store them in a centralized location. Applications access that information to make authorization decisions.

#### What is Attribute-Based Access Control?

In [Attribute-Based Access Control](https://en.wikipedia.org/wiki/Attribute-based_access_control) (ABAC), permissions are granted based on a set of attributes that a user or resource possesses. For example, a user assigned both `marketing` and `manager` attributes is entitled to publish and delete posts that have a `marketing` attribute.

Applications implementing ABAC need to retrieve information stored in multiple data sources - like RBAC services, user directories, and application-specific data sources - to make authorization decisions.

#### What is Policy-Based Access Control?

Policy-Based Access Control (PBAC) is the ability to manage authorization policies in a centralized way that’s external to the application code. Most implementations of ABAC are also PBAC.

#### What is Relationship-Based Access Control?

[Relationship-Based Access Control](https://en.wikipedia.org/wiki/Relationship-based_access_control) (ReBAC) enables user access rules to be conditional on relations that a given user has with a given object _and_ that object's relationship with other objects. For example, a given user can view a given document if the user has access to the document's parent folder.

ReBAC is a superset of RBAC: you can fully implement RBAC with ReBAC. 
ReBAC also lets you natively solve for ABAC when attributes can be expressed in the form of relationships. For example ‘a user’s manager’, ‘the parent folder’,  ‘the owner of a document’, ‘the user’s department’ can be defined as relationships. 

<ProductName format={ProductNameFormat.ShortForm}/> extends ReBAC by making it simpler to express additional ABAC scenarios using [Conditions](https://github.com/openfga/openfga.dev/blob/main/./modeling/conditions.mdx) or [Contextual Tuples](https://github.com/openfga/openfga.dev/blob/main/./modeling/token-claims-contextual-tuples.mdx).

ReBAC can also be considered PBAC, as authorization policies are centralized.

#### What is Zanzibar?

[Zanzibar](https://research.google/pubs/pub48190/) is Google's global authorization system across Google's product suite. It’s based on ReBAC and uses object-relation-user tuples to store relationship data, then checks those relations for a match between a user and an object. For more information, see [Zanzibar Academy](https://zanzibar.academy).

ReBAC systems based on Zanzibar store the data necessary to make authorization decisions in a centralized database. Applications only need to call an API to make authorization decisions.

<ProductName format={ProductNameFormat.ShortForm}/> is an example of a Zanzibar-based authorization system.

<RelatedSection
  description="Learn about {ProductName}."
  relatedLinks={[
    {
      title: '{ProductName} Concepts',
      description: 'Learn about the {ProductName} Concepts',
      link: './concepts',
      id: './concepts',
    },    
    {
      title: 'Modeling: Getting Started',
      description: 'Learn about how to get started with modeling your permission system in {ProductName}.',
      link: './getting-started',
      id: './getting-started',
    }
  ]}
  />
  

<!-- End of openfga/openfga.dev/docs/content/authorization-concepts.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/community.mdx -->

---
title: Community
sidebar_position: 2
slug: /community
description: Learn how to engage with the OpenFGA Community
---
### OpenFGA Community

#### Slack (CNCF Community)
The OpenFGA community has a channel in the [CNCF](https://cncf.io) Slack.

If you don't have access to the CNCF Slack you can request an invitation [here](https://slack.cncf.io). You can join the community in the [#openfga](https://cloud-native.slack.com/archives/C06G1NNH47N) channel.

#### GitHub Discussions
You can also use [GitHub discussions](https://github.com/orgs/openfga/discussions) to ask questions and submit product ideas.

#### X (formerly Twitter)
Follow us on X to get the latest updates on all things OpenFGA. [@OpenFGA](https://twitter.com/OpenFGA).

#### YouTube
Subscribe to [the OpenFGA YouTube Channel](https://www.youtube.com/@OpenFGA) to see our latest videos and recordings.

#### LinkedIn
Follow us on [LinkedIn](https://www.linkedin.com/company/openfga/) for the latest updates, community highlights, and insights into fine-grained authorization.

#### Mastodon
For the Fediverse fans among you, follow us on Mastodon at [@openfga@mastodon.social](https://mastodon.social/@openfga)!

#### Monthly Community Meetings
We hold a monthly community meeting on the second Thursday of every month @ [11am Eastern Time (US)](https://www.worldtimebuddy.com/?qm=1&lid=12,100,5,6,8&h=5&sln=11-12&hf=1).

* [Calendar](https://zoom-lfx.platform.linuxfoundation.org/meetings/openfga) 
* [Agenda](https://docs.google.com/document/d/1Y6rbD0xpGLVl-7CmeMgxi56_a0ibIQ_RojvWBbT9MZk/edit#)
* [Zoom Link](https://zoom-lfx.platform.linuxfoundation.org/meetings/openfga)
* [Recordings of Previous Meetings](https://www.youtube.com/playlist?list=PLUR5l-oTFZqUneyHz-h4WzaJssgxBXdxB)

Read more details [here](https://github.com/openfga/community/blob/main/community-meetings.md)


<!-- End of openfga/openfga.dev/docs/content/community.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/concepts.mdx -->

---
title: Concepts
sidebar_position: 2
slug: /concepts
description: Learning about FGA concepts
---

import {
  AuthzModelSnippetViewer,
  CheckRequestViewer,
  DocumentationNotice,
  IntroductionSection,
  ListObjectsRequestViewer,
  Playground,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  RelationshipTuplesViewer,
  UpdateProductNameInLinks,
  ListUsersRequestViewer
} from '@components/Docs';

### Concepts

<DocumentationNotice />

The <ProductName format={ProductNameFormat.ProductLink}/> service answers <IntroductionSection linkName="authorization" section="authentication-and-authorization"/> [checks](#what-is-a-check-request) by determining whether a **[relationship](#what-is-a-relation)** exists between an [object](#what-is-an-object) and a [user](#what-is-a-user). Checks reference your **[authorization model](#what-is-an-authorization-model)** against your **[relationship tuples](#what-is-a-relationship-tuple)** for authorization authority. Below are explanations of basic FGA concepts, like type and authorization model, and a [playground](https://play.fga.dev/) to test your knowledge.

<Playground />

<details>
<summary>

#### What Is A Type?

A **type** is a string. It defines a class of objects with similar characteristics.

</summary>

The following are examples of types:

- `workspace`
- `repository`
- `organization`
- `document`

</details>

<details>
<summary>

#### What Is A Type Definition?

A **type definition** defines all possible relations a user or another object can have in relation to this type.

</summary>

Below is an example of a type definition:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type: 'document',
    relations: {
      viewer: {
        this: {},
      },
      commenter: {
        this: {},
      },
      editor: {
        this: {},
      },
      owner: {
        this: {},
      },
    },
    metadata: {
      relations: {
        viewer: { directly_related_user_types: [{ type: 'user' }] },
        commenter: { directly_related_user_types: [{ type: 'user' }] },
        editor: { directly_related_user_types: [{ type: 'user' }] },
        owner: { directly_related_user_types: [{ type: 'user' }] },
      },
    },
  }} skipVersion={true}
/>

</details>

<details>
<summary>

#### What Is An Authorization Model?

An **authorization model** combines one or more type definitions. This is used to define the permission model of a system.

</summary>

Below is an example of an authorization model:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'document',
        relations: {
          viewer: {
            this: {},
          },
          commenter: {
            this: {},
          },
          editor: {
            this: {},
          },
          owner: {
            this: {},
          },
        },
        metadata: {
          relations: {
            viewer: { directly_related_user_types: [{ type: 'domain', relation: 'member' }, { type: 'user' }] },
            commenter: { directly_related_user_types: [{ type: 'domain', relation: 'member' }, { type: 'user' }] },
            editor: { directly_related_user_types: [{ type: 'domain', relation: 'member' }, { type: 'user' }] },
            owner: { directly_related_user_types: [{ type: 'domain', relation: 'member' }, { type: 'user' }] },
          },
        },
      },
      {
        type: 'domain',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'user',
      },
    ],
  }}
/>

Together with [relationship tuples](#what-is-a-relationship-tuple), the authorization model determines whether a [relationship](#what-is-a-relationship) exists between a [user](#what-is-a-user) and an [object](#what-is-an-object).

<ProductName format={ProductNameFormat.LongForm}/> uses two different syntaxes to define the authorization model:

- A JSON syntax accepted by the <ProductName format={ProductNameFormat.ShortForm}/> API that closely follows the original syntax in the [Zanzibar Paper](https://research.google/pubs/pub48190/). For more information, see [Equivalent Zanzibar Concepts](https://github.com/openfga/openfga.dev/blob/main/./configuration-language.mdx#equivalent-zanzibar-concepts).
- A simpler-to-use DSL that's accepted by both the [OpenFGA VS Code extension](https://marketplace.visualstudio.com/items?itemName=openfga.openfga-vscode) and [OpenFGA CLI](https://github.com/openfga/cli/) and offers syntax highlighting and validation in the VS Code extension. The DSL is used in the [Sample Stores](https://github.com/openfga/sample-stores) modeling examples and is translated to API-supported syntax using the CLI or [OpenFGA language](https://github.com/openfga/language) before being sent to the API.

Click here to learn more about the <UpdateProductNameInLinks link="./configuration-language" name="{ProductName} Configuration Language" />.

</details>

<details>
<summary>

#### What Is A Store?

A **store** is an <ProductName format={ProductNameFormat.LongForm}/> entity used to organize authorization check data.

</summary>

Each store contains one or more versions of an [authorization model](#what-is-an-authorization-model) and can contain various [relationship tuples](#what-is-a-relationship-tuple). Store data cannot be shared across stores; we recommended storing all data that may be related or affect your authorization result in a single store.

Separate stores can be created for separate authorization needs or isolated environments, e.g. development/prod.

</details>

<details>
<summary>

#### What Is An Object?

An **object** represents an entity in the system. Users' relationships to it are defined by relationship tuples and the authorization model.

</summary>

An object is a combination of a [type](#what-is-a-type) and an identifier.

For example:

- `workspace:fb83c013-3060-41f4-9590-d3233a67938f`
- `repository:auth0/express-jwt`
- `organization:org_ajUc9kJ`
- `document:new-roadmap`

[User](#what-is-a-user), [relation](#what-is-a-relation) and object are the building blocks for [relationship tuples](#what-is-a-relationship-tuple).

For an example, see [Direct Access](https://github.com/openfga/openfga.dev/blob/main/./modeling/direct-access.mdx).

</details>

<details>
<summary>

#### What Is A User?

A **user** is an entity in the system that can be related to an object.

</summary>

A user is a combination of a [type](#what-is-a-type), an identifier, and an optional relation.

For example,

- any identifier: e.g. `user:anne` or `user:4179af14-f0c0-4930-88fd-5570c7bf6f59`
- any object: e.g. `workspace:fb83c013-3060-41f4-9590-d3233a67938f`, `repository:auth0/express-jwt` or `organization:org_ajUc9kJ`
- a group or a set of users (also called a **userset**): e.g. `organization:org_ajUc9kJ#members`, which represents the set of users related to the object `organization:org_ajUc9kJ` as `member`
- everyone, using the special syntax: `*`

User, [relation](#what-is-a-relation) and [object](#what-is-an-object) are the building blocks for [relationship tuples](#what-is-a-relationship-tuple).

For more information, see [Direct Access](https://github.com/openfga/openfga.dev/blob/main/./modeling/direct-access.mdx).

</details>

<details>
<summary>

#### What Is A Relation?

A **relation** is a string defined in the type definition of an authorization model. Relations define a possible relationship between an object (of the same type as the type definition) and a user in the system.

</summary>

Examples of relation:

- User can be a `reader` of a document
- Team can `administer` a repo
- User can be a `member` of a team

</details>

<details>
<summary>

#### What Is A Relation Definition?

A **relation definition** lists the conditions or requirements under which a relationship is possible.

</summary>

For example:

- `editor` describing a possible relationship between a user and an object in the `document` type allows the following:
  - **user identifier to object relationship**: the user id `anne` of type `user` is related to the object `document:roadmap` as `editor`
  - **object to object relationship**: the object `application:ifft` is related to the object `document:roadmap` as `editor`
  - **userset to object relationship**: the userset `organization:auth0.com#member` is related to `document:roadmap` as `editor`
    - indicates that the set of users who are related to the object `organization:auth0.com` as `member` are related to the object `document:roadmap` as `editor`s
    - allows for potential solutions to use-cases like sharing a document internally with all members of a company or a team
  - **everyone to object relationship**: everyone (`*`) is related to `document:roadmap` as `editor`
    - this is how one could model publicly editable documents

These would be defined in the [authorization model](#what-is-an-authorization-model):

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'document',
        relations: {
          viewer: {
            this: {},
          },
          commenter: {
            this: {},
          },
          editor: {
            this: {},
          },
          owner: {
            this: {},
          },
        },
        metadata: {
          relations: {
            viewer: { directly_related_user_types: [{ type: 'user' }] },
            commenter: { directly_related_user_types: [{ type: 'user' }] },
            editor: { directly_related_user_types: [{ type: 'team', relation: 'member' }, { type: 'user' }] },
            owner: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'user',
      },
      {
        type: 'team',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }} skipVersion={true}
/>

:::info

There are four relations in the document type configuration: `viewer`, `commenter`, `editor` and `owner`. The `editor` relation exists when the report is directly assigned to the user or for any member of an assigned team.

:::

[User](#what-is-a-user), relation and [object](#what-is-an-object) are the building blocks for [relationship tuples](#what-is-a-relationship-tuple).

For an example, see [Direct Access](https://github.com/openfga/openfga.dev/blob/main/./modeling/direct-access.mdx).

</details>

<details>
<summary>

#### What Is A Directly Related User Type?

A **directly related user type** is an array specified in the type definition to indicate which types of users can be directly related to that relation.

</summary>

For the following model, only [relationship tuples](#what-is-a-relationship-tuple) with [user](#what-is-a-user) of [type](#what-is-a-type) `user` may be assigned to document.

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'document',
        relations: {
          viewer: {
            this: {},
          },
        },
        metadata: {
          relations: {
            viewer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }} skipVersion={true}
/>

A relationship tuple with user `user:anne` or `user:3f7768e0-4fa7-4e93-8417-4da68ce1846c` may be written for objects with type `document` and relation `viewer`, so writing `{"user": "user:anne","relation":"viewer","object":"document:roadmap"}` succeeds.
A relationship tuple with a disallowed user type for the `viewer` relation on objects of type `document` - for example `workspace:auth0` or `folder:planning#editor` - will be rejected, so writing `{"user": "folder:product","relation":"viewer","object":"document:roadmap"}` will fail.
This affects only relations that are [directly related](#what-are-direct-and-implied-relationships) and have [direct relationship type restrictions](https://github.com/openfga/openfga.dev/blob/main/./configuration-language.mdx#direct-relationship-type-restrictions) in their relation definition.

</details>

<details>
<summary>
#### What is a Condition?

A **condition** is a function composed of one or more parameters and an expression. Every condition evaluates to a boolean outcome, and expressions are defined using [Google's Common Expression Language (CEL)](https://github.com/google/cel-spec).
</summary>

In the following snippet `less_than_hundred` defines a Condition that evaluates to a boolean outcome. The provided parameter `x`, defined as an integer type, is used in the boolean expression `x < 100`. The condition returns a truthy outcome if the expression returns a truthy outcome, but is otherwise false.
```
condition less_than_hundred(x: int) {
  x < 100
}
```
</details>

<details>
<summary>

#### What Is A Relationship Tuple?

A **relationship tuple** is a base tuple/triplet consisting of a user, relation, and object. Tuples may add an optional condition, like [Conditional Relationship Tuples](#what-is-a-conditional-relationship-tuple). Relationship tuples are written and stored in <ProductName format={ProductNameFormat.ShortForm}/>.

</summary>

A relationship tuple consists of:

- a **[user](#what-is-a-user)**, e.g. `user:anne`, `user:3f7768e0-4fa7-4e93-8417-4da68ce1846c`, `workspace:auth0` or `folder:planning#editor`
- a **[relation](#what-is-a-relation)**, e.g. `editor`, `member` or `parent_workspace`
- an **[object](#what-is-an-object)**, e.g `repo:auth0/express_jwt`, `domain:auth0.com` or `channel:marketing`
- a **[condition](#what-is-a-condition)** (optional), e.g. `{"condition": "in_allowed_ip_range", "context": {...}}`

An [authorization model](#what-is-an-authorization-model), together with relationship tuples, determine whether a [relationship](#what-is-a-relationship) exists between a [user](#what-is-a-user) and an [object](#what-is-an-object).

Relationship tuples are usually shown in the following format:

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      user: 'user:anne',
      relation: 'editor',
      object: 'document:new-roadmap',
    },
  ]}
/>

For more information, see [Direct Access](https://github.com/openfga/openfga.dev/blob/main/./modeling/direct-access.mdx).

</details>

<details>
<summary>
#### What Is A Conditional Relationship Tuple?

A **conditional relationship tuple** is a [relationship tuple](#what-is-a-relationship-tuple) that represents a [relationship](#what-is-a-relationship) conditioned upon the evaluation of a [condition](#what-is-a-condition).
</summary>

If a relationship tuple is conditioned, then that condition must to a truthy outcome for the relationship tuple to be permissible.

The following relationship tuple is a conditional relationship tuple because it is conditioned on `less_than_hundred`. If the expression for `less_than_hundred` is defined as `x < 100`, then the relationship is permissible because the expression - `20 < 100` - evaluates to a truthy outcome.

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      user: 'user:anne',
      relation: 'editor',
      object: 'document:new-roadmap',
      condition: {
        "name": "less_than_hundred",
        "context": {
          "x": 20
        }
      }
    },
  ]}
/>

</details>

<details>
<summary>

#### What Is A Relationship?

A **relationship** is the realization of a relation between a user and an object.

</summary>

An [authorization model](#what-is-an-authorization-model), together with [relationship tuples](#what-is-a-relationship-tuple), determine whether a relationship exists between a user and an object. Relationships may be [direct](#what-are-direct-and-implied-relationships) or [implied](#what-are-direct-and-implied-relationships).

</details>

<details>
<summary>

#### What Are Direct And Implied Relationships?

A **direct relationship** (R) between user X and object Y means the relationship tuple (user=X, relation=R, object=Y) exists, and the <ProductName format={ProductNameFormat.ShortForm}/> authorization model for that relation allows the direct relationship because of [direct relationship type restrictions](https://github.com/openfga/openfga.dev/blob/main/./configuration-language.mdx#direct-relationship-type-restrictions).

An **implied (or computed) relationship** (R) exists between user X and object Y if user X is related to an object Z that is in a direct or implied relationship with object Y, and the <ProductName format={ProductNameFormat.ShortForm}/> authorization model allows it.

</summary>

- `user:anne` has a direct relationship with `document:new-roadmap` as `viewer` if the [type definition](#what-is-a-type-definition) allows it with [direct relationship type restrictions](https://github.com/openfga/openfga.dev/blob/main/./configuration-language.mdx#direct-relationship-type-restrictions), and one of the following [relationship tuples](#what-is-a-relationship-tuple) exist:

  - <RelationshipTuplesViewer
      relationshipTuples={[
        {
          _description: 'Anne of type user is directly related to the document',
          user: 'user:anne',
          relation: 'viewer',
          object: 'document:new-roadmap',
        },
      ]}
    />

  - <RelationshipTuplesViewer
      relationshipTuples={[
        {
          _description: 'Everyone (`*`) of type user is directly related to the document',
          user: 'user:*',
          relation: 'viewer',
          object: 'document:new-roadmap',
        },
      ]}
    />

  - <RelationshipTuplesViewer
      relationshipTuples={[
        {
          _description: 'The userset is directly related to this document',
          user: 'team:product#member',
          relation: 'viewer',
          object: 'document:new-roadmap',
        },
        {
          _description: 'AND Anne of type user is a member of the userset team:product#member',
          user: 'user:anne',
          relation: 'member',
          object: 'team:product#member',
        },
      ]}
    />

- `user:anne` has an **implied relationship** with `document:new-roadmap` as `viewer` if the type definition allows it, and the presence of relationship tuples satisfying the relationship exist.

  For example, assume the following type definition:

  <AuthzModelSnippetViewer
    configuration={{
      schema_version: '1.1',
      type_definitions: [
        {
          type: 'document',
          relations: {
            viewer: {
              union: {
                child: [
                  {
                    // a user can be assigned a direct `viewer` relation, i.e., not implied through another relation
                    this: {},
                  },
                  {
                    // a user who is related as an editor is also implicitly related as a viewer
                    computedUserset: {
                      relation: 'editor',
                    },
                  },
                ],
              },
            },
            editor: {
              this: {},
            },
          },
          metadata: {
            relations: {
              editor: { directly_related_user_types: [{ type: 'user' }] },
              viewer: { directly_related_user_types: [{ type: 'user' }] },
            },
          },
        },
      ],
    }} skipVersion={true}
  />

  And assume the following relationship tuple exists in the system:

  <RelationshipTuplesViewer
    relationshipTuples={[
      {
        user: 'user:anne',
        relation: 'editor',
        object: 'document:new-roadmap',
      },
    ]}
  />

  In this case, the [relationship](#what-is-a-relationship) between `user:anne` and `document:new-roadmap` as a `viewer` is implied from the direct `editor` relationship `user:anne` has with that same document. Thus, the following request to [check](#what-is-a-check-request) whether a viewer relationship exists between `user:anne` and `document:new-roadmap` will return `true`.

  <CheckRequestViewer user={'user:anne'} relation={'viewer'} object={'document:new-roadmap'} allowed={true} />

</details>

<details>
<summary>

#### What Is A Check Request?

A **check request** is a call to the <ProductName format={ProductNameFormat.LongForm}/> check endpoint, returning whether the user has a certain relationship with an object.

</summary>

Check requests use the `check` methods in the <ProductName format={ProductNameFormat.ShortForm}/> SDKs ([JavaScript SDK](https://www.npmjs.com/package/@openfga/sdk)/[Go SDK](https://github.com/openfga/go-sdk)/[.NET SDK](https://www.nuget.org/packages/OpenFga.Sdk)) by manually calling the [check endpoint](https://github.com/openfga/openfga.dev/blob/main//api/service#Relationship%20Queries/Check) using curl or in your code. The check endpoint responds with `{ "allowed": true }` if a relationship exists, and with `{ "allowed": false }` if the relationship does not.

For example, the following will check whether `anne` of type user has a `viewer` relation to `document:new-roadmap`:

<CheckRequestViewer user={'user:anne'} relation={'viewer'} object={'document:new-roadmap'} allowed={true} />

For more information, see the [Relationship Queries page](https://github.com/openfga/openfga.dev/blob/main/./interacting/relationship-queries.mdx) and the official [Check API Reference](https://github.com/openfga/openfga.dev/blob/main//api/service#Relationship%20Queries/Check).

</details>

<details>
<summary>

#### What Is A List Objects Request?

A **list objects request** is a call to the <ProductName format={ProductNameFormat.LongForm}/> list objects endpoint that returns all objects of a given type that a user has a specified relationship with.

</summary>

List objects requests are completed using the `listobjects` methods in the <ProductName format={ProductNameFormat.ShortForm}/> SDKs ([JavaScript SDK](https://www.npmjs.com/package/@openfga/sdk)/[Go SDK](https://github.com/openfga/go-sdk)/[.NET SDK](https://www.nuget.org/packages/OpenFga.Sdk)) by manually calling the [list objects endpoint](https://github.com/openfga/openfga.dev/blob/main//api/service#Relationship%20Queries/ListObjects) using curl or in your code.

The list objects endpoint responds with a list of objects for a given type that the user has the specified relationship with.

For example, the following returns all the objects with document type for which `anne` of type user has a `viewer` relation with:

<ListObjectsRequestViewer
  authorizationModelId="01HVMMBCMGZNT3SED4Z17ECXCA"
  objectType="document"
  relation="viewer"
  user="user:anne"
  expectedResults={['document:otherdoc', 'document:planning']}
/>

For more information, see the [Relationship Queries page](https://github.com/openfga/openfga.dev/blob/main/./interacting/relationship-queries.mdx) and the [List Objects API Reference](https://github.com/openfga/openfga.dev/blob/main//api/service#Relationship%20Queries/ListObjects).

</details>
<details>
<summary>

#### What Is A List Users Request?

A **list users request** is a call to the <ProductName format={ProductNameFormat.LongForm}/> list users endpoint that returns all users of a given type that have a specified relationship with an object.

</summary>

List users requests are completed using the relevant `ListUsers` method in SDKs, the `fga query list-users` command in the CLI, or by manually calling the [ListUsers endpoint](https://github.com/openfga/openfga.dev/blob/main//api/service#Relationship%20Queries/ListUsers) using curl or in your code.

The list users endpoint responds with a list of users for a given type that have the specificed relationship with an object.

For example, the following returns all the users of type `user` that have the `viewer` relationship for `document:planning`:

<ListUsersRequestViewer
  authorizationModelId="01HVMMBCMGZNT3SED4Z17ECXCA"
  objectType="document"
  objectId="planning"
  relation="viewer"
  userFilterType="user"
  expectedResults={{
    users: [
      { object: { type: "user", id: "anne" }}, 
      { object: { type: "user", id: "beth" }}
    ]
  }}
/>

For more information, see the [ListUsers API Reference](https://github.com/openfga/openfga.dev/blob/main//api/service#Relationship%20Queries/ListUsers).

</details>
<details>
<summary>

#### What Are Contextual Tuples?

Contextual tuples are tuples that can be added to a Check request, a ListObjects request, a ListUsers request, or an Expand request. They only exist within the context of that particular request and are not persisted in the datastore.

</summary>

Similar to [relationship tuples](#what-is-a-relationship-tuple), contextual tuples are composed of a user, relation and object. Unlike relationship tuples, they are not written to the store. However, if contextual tuples are sent alongside a check request in the context of a particular check request, they are treated if they had been written in the store.

<!-- markdown-link-check-disable -->

For more information, see [Contextual and Time-Based Authorization](https://github.com/openfga/openfga.dev/blob/main/./modeling/contextual-time-based-authorization.mdx), [Authorization Through Organization Context](https://github.com/openfga/openfga.dev/blob/main/./modeling/organization-context-authorization.mdx) and [Check API Request Documentation](https://github.com/openfga/openfga.dev/blob/main//api/service#Relationship%20Queries/Check).

<!-- markdown-link-check-enable -->

</details>

<details>
<summary>

#### What Is Type Bound Public Access?

In <ProductName format={ProductNameFormat.LongForm}/>, type bound public access (represented by `<type>:*`) is a special <ProductName format={ProductNameFormat.ShortForm}/> syntax meaning "every object of [type]" when invoked as a user within a relationship tuple. For example, `user:*` represents every object of type `user` , including those not currently present in the system.

</summary>

For example, to indicate `document:new-roadmap` is publicly writable (in other words, has everyone of type `user` as an editor, add the following [relationship tuple](#what-is-a-relationship-tuple):

<RelationshipTuplesViewer
  relationshipTuples={[
    {
      user: 'user:*',
      relation: 'editor',
      object: 'document:new-roadmap',
    },
  ]}
/>

Note: `<type>:*` cannot be used in the `relation` or `object` properties. In addition, `<type>:*` cannot be used as part of a userset in the tuple's user field.
For more information, see [Modeling Public Access](https://github.com/openfga/openfga.dev/blob/main/./modeling/public-access.mdx) and [Advanced Modeling: Modeling Google Drive](https://github.com/openfga/openfga.dev/blob/main/./modeling/advanced/gdrive.mdx).

</details>

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how object-to-object relationships can be used."
  relatedLinks={[
    {
      title: 'Authorization Concepts',
      description: 'Learn about Authorization.',
      link: './authorization-concepts',
      id: './authorization-concepts',
    },
    {
      title: 'Configuration Language',
      description: 'Learning about the FGA configuration language',
      link: './configuration-language',
      id: './configuration-language',
    },
    {
      title: 'Direct access',
      description: 'Get started with modeling your permission system in {ProductName}',
      link: './modeling/direct-access',
      id: './modeling/direct-access',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/concepts.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/configuration-language.mdx -->

---
title: Configuration Language
sidebar_position: 2
slug: /configuration-language
description: Learning about the FGA configuration language and using it to build a representation of a system's authorization model
---

import {
  AuthzModelSnippetViewer,
  CheckRequestViewer,
  DocumentationNotice,
  ProductConcept,
  ProductName,
  ProductNameFormat,
  RelatedSection,
  RelationshipTuplesViewer,
  SyntaxFormat,
  UpdateProductNameInLinks,
  WriteRequestViewer,
} from '@components/Docs';

### Configuration Language

<DocumentationNotice />

<ProductName format={ProductNameFormat.LongForm}/>'s Configuration Language builds a representation of a system's <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" />, which informs <UpdateProductNameInLinks link="/api/service" name="{ProductName}'s API" /> on the <ProductConcept section="what-is-a-type" linkName="object types" /> in the system and how they relate to each other. The Configuration Language describes the <ProductConcept section="what-is-a-relation" linkName="relations" /> possible for an object of a given type and lists the conditions under which one is related to that object.

The Configuration Language can be presented in **DSL** or **JSON** syntax. The JSON syntax is accepted by the API and closely tracks the language in the [Zanzibar paper](https://research.google/pubs/pub48190/). The DSL adds syntactic sugar on top of JSON for ease of use, but compiles down to JSON before being sent to <ProductName format={ProductNameFormat.ShortForm}/>'s API. JSON syntax is used to call API directly or through the [SDKs](https://github.com/openfga/openfga.dev/blob/main/./getting-started), while DSL is used to interact with <ProductName format={ProductNameFormat.ShortForm}/> in the [Playground](https://play.fga.dev/), the [CLI](https://github.com/openfga/cli), and the IDE extensions for [Visual Studio Code](https://marketplace.visualstudio.com/items?itemName=openfga.openfga-vscode) and [IntelliJ](https://plugins.jetbrains.com/plugin/24394-openfga). They can be switched between throughout this documentation. 

Please familiarize yourself with basic <ProductConcept /> and [How to get started on modeling](https://github.com/openfga/openfga.dev/blob/main/./modeling/getting-started.mdx) before starting this guide.

#### What Does The Configuration Language Look Like?

Below is a sample authorization model. The next sections discuss the basics of the <ProductName format={ProductNameFormat.ShortForm}/> configuration language.

<AuthzModelSnippetViewer
  syntaxesToShow={[SyntaxFormat.Dsl, SyntaxFormat.Json]}
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'domain',
        relations: {
          member: {
            this: {},
          },
        },
        metadata: {
          relations: {
            member: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
      {
        type: 'folder',
        relations: {
          can_share: {
            computedUserset: {
              object: '',
              relation: 'writer',
            },
          },
          owner: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'parent_folder',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'owner',
                    },
                  },
                },
              ],
            },
          },
          parent_folder: {
            this: {},
          },
          viewer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    object: '',
                    relation: 'writer',
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'parent_folder',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'viewer',
                    },
                  },
                },
              ],
            },
          },
          writer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    object: '',
                    relation: 'owner',
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'parent_folder',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'writer',
                    },
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            owner: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
            parent_folder: { directly_related_user_types: [{ type: 'folder' }] },
            viewer: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
            writer: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
          },
        },
      },
      {
        type: 'document',
        relations: {
          can_share: {
            computedUserset: {
              object: '',
              relation: 'writer',
            },
          },
          owner: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'parent_folder',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'owner',
                    },
                  },
                },
              ],
            },
          },
          parent_folder: {
            this: {},
          },
          viewer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    object: '',
                    relation: 'writer',
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'parent_folder',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'viewer',
                    },
                  },
                },
              ],
            },
          },
          writer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    object: '',
                    relation: 'owner',
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'parent_folder',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'writer',
                    },
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            owner: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
            parent_folder: { directly_related_user_types: [{ type: 'folder' }] },
            viewer: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
            writer: { directly_related_user_types: [{ type: 'user' }, { type: 'domain', relation: 'member' }] },
          },
        },
      },
    ],
  }}
/>

:::info

The authorization model describes four <ProductConcept section="what-is-a-type" linkName="types" /> of objects: `user`, `domain`, `folder` and `document`.

The `domain` <ProductConcept section="what-is-a-type-definition" linkName="type definition" /> has a single <ProductConcept section="what-is-a-relation" linkName="relation" /> called `member` that only allows <ProductConcept section="what-are-direct-and-implied-relationships" linkName="direct relationships" />.

The `folder` and `document` type definitions each have five relations: `parent_folder`, `owner`, `writer`, `viewer` and `can_share`.

:::

##### Direct Relationship Type Restrictions

When used at the beginning of a <ProductConcept section="what-is-a-relation-definition" linkName="relation definition" />, `[<string, <string>, ...]` allows <ProductConcept section="what-are-direct-and-implied-relationships" linkName="direct relationships" /> by the objects of these specified types. The strings can be in one of three formats:
- `<type>`: indicates that tuples relating objects of those types as users can be written. For example, `group:marketing` can be related if `group` is in the type restrictions.
- `<type:*>`: indicates that a tuple relating all objects of that type can be written. For example, `user:*` can be added if `user:*` is in the type restrictions.
- `<type>#<relation>`: indicates tuples with sets of users related to an object of that type by that particular relation. For example, `group:marketing#member` can be added if `group#member` is in the type restrictions.

If no direct relationship type restrictions are specified, direct relationships are disallowed and tuples cannot be written relating other objects of this particular relation with objects of this type.

:::info

`[<type1>, <type2>, ...]` in the <ProductName format={ProductNameFormat.ShortForm}/> DSL translates to `this` in the <ProductName format={ProductNameFormat.ShortForm}/> API syntax.

:::

For example, below is a snippet of the `team` type:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type: 'team',
    relations: {
      member: {
        this: {},
      },
    },
    metadata: {
      relations: {
        member: { directly_related_user_types: [{ type: 'user' }, { type: 'user:*'}, { type: 'team', relation: 'member' }] },
      },
    },
  }} skipVersion={true}
/>

The `team` <ProductConcept section="what-is-a-type-definition" linkName="type definition" /> above defines all the <ProductConcept section="what-is-a-relation" linkName="relations" /> that <ProductConcept section="what-is-a-user" linkName="users" /> can have with an _<ProductConcept section="what-is-an-object" linkName="object" />_ of type `team`. In this example, the relation is `member`.

Because of the `[user, team#member]` direct relationship type restrictions used, a user in the system can have a **<ProductConcept section="what-are-direct-and-implied-relationships" linkName="direct relationship" />** with the `team` type as a `member` for objects of: 
- type `user`
- the `user` <ProductConcept section="what-is-type-bound-public-access" linkName="type bound public access" /> (`user:*`)
- [usersets](https://github.com/openfga/openfga.dev/blob/main/./modeling/building-blocks/usersets.mdx) that have a `team` type and a `member` relation (e.g. `team:product#member`)

In the type definition snippet above, `anne` is a `member` of `team:product` if any of the following relationship tuple sets exist:

- <RelationshipTuplesViewer
    relationshipTuples={[
      {
        user: 'user:anne',
        relation: 'member',
        object: 'team:product',
        _description: 'Anne is directly related to the product team as a member',
      },
    ]}
  />

- <RelationshipTuplesViewer
    relationshipTuples={[
      {
        user: 'user:*',
        relation: 'member',
        object: 'team:product',
        _description: 'Everyone (`*`) is directly related to the product team as a member',
      }
    ]}
  />

- <RelationshipTuplesViewer
    relationshipTuples={[
      {
        user: 'team:contoso#member',
        relation: 'member',
        object: 'team:product',
        _description: 'Members of the contoso team are members of the product team',
      },
      {
        user: 'user:anne',
        relation: 'member',
        object: 'team:contoso',
        _description: 'Anne is a member of the contoso team',
      },
    ]}
  />

For more examples, see [Modeling Building Blocks: Direct Relationships](https://github.com/openfga/openfga.dev/blob/main/./modeling/building-blocks/direct-relationships.mdx).

##### Referencing Other Relations On The Same Object

The same object can also reference other relations. Below is a simplified `document` type definition:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type: 'document',
    relations: {
      editor: {
        this: {},
      },
      viewer: {
        union: {
          child: [
            { this: {} },
            {
              computedUserset: {
                relation: 'editor',
              },
            },
          ],
        },
      },
      can_rename: {
        computedUserset: {
          relation: 'editor',
        },
      },
    },
    metadata: {
      relations: {
        editor: { directly_related_user_types: [{ type: 'user' }] },
        viewer: { directly_related_user_types: [{ type: 'user' }] },
      },
    },
  }} skipVersion={true}
/>

Above, `document` <ProductConcept section="what-is-a-type-definition" linkName="type definition" /> defines all the <ProductConcept section="what-is-a-relation" linkName="relations" /> that <ProductConcept section="what-is-a-user" linkName="users" /> can have with an <ProductConcept section="what-is-an-object" linkName="object" /> of type `document`. In this case, the relations are `editor`, `viewer` and `can_rename`. The `viewer` and `can_rename` relation definitions both reference `editor`, which is another relation of the same type.

:::info

`can_rename` does not reference the [direct relationship type restrictions](#direct-relationship-type-restrictions), which means a user cannot be directly assigned this relation and it must be inherited when the `editor` relation is assigned. Conversely, the `viewer` relation allows both direct and indirect relationships using the [Union Operator](#the-union-operator).

:::

In the type definition snippet above, `anne` is a `viewer` of `document:new-roadmap` if any one of the following relationship tuple sets exists:

- _anne_ is an _editor_ of _document:new-roadmap_

  <RelationshipTuplesViewer
    relationshipTuples={[
      {
        user: 'user:anne',
        relation: 'editor',
        object: 'document:new-roadmap',
        _description: 'Anne is an editor of the new-roadmap document',
      },
    ]}
  />

- _anne_ is a _viewer_ of _document:new-roadmap_
  <RelationshipTuplesViewer
    relationshipTuples={[
      {
        user: 'user:anne',
        relation: 'viewer',
        object: 'document:new-roadmap',
        _description: 'Anne is a viewer of the new-roadmap document',
      },
    ]}
  />

`anne` has a `can_rename` relationship with `document:new-roadmap` only if `anne` has an `editor` relationship with the document:

- _anne_ is an _editor_ of _document:new-roadmap_
  <RelationshipTuplesViewer
    relationshipTuples={[
      {
        user: 'user:anne',
        relation: 'editor',
        object: 'document:new-roadmap',
        _description: 'Anne is an editor of thew new-roadmap document',
      },
    ]}
  />

For more examples, see [Modeling Building Blocks: Concentric Relationships](https://github.com/openfga/openfga.dev/blob/main/./modeling/building-blocks/concentric-relationships.mdx), [Modeling: Roles and Permissions](https://github.com/openfga/openfga.dev/blob/main/./modeling/roles-and-permissions.mdx) and [Advanced Modeling: Google Drive](https://github.com/openfga/openfga.dev/blob/main/./modeling/advanced/gdrive.mdx).

##### Referencing Relations On Related Objects

Another set of <ProductConcept section="what-are-direct-and-implied-relationships" linkName="indirect relationships" /> are made possible by referencing relations to other objects.

The syntax is `X from Y` and requires that:

- the other object is related to the current object as `Y`
- the _user_ is related to another object as `X`

See the _authorization model_ below.

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'user',
      },
      {
        type: 'folder',
        relations: {
          viewer: {
            this: {},
          },
        },
        metadata: {
          relations: {
            viewer: { directly_related_user_types: [{ type: 'user' }, { type: 'folder', relation: 'viewer' }] },
          },
        },
      },
      {
        type: 'document',
        relations: {
          parent_folder: {
            this: {},
          },
          viewer: {
            union: {
              child: [
                { this: {} },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'parent_folder',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'viewer',
                    },
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            parent_folder: { directly_related_user_types: [{ type: 'folder' }] },
            viewer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

The snippet below (taken from the authorization model above) states that viewers of a document are both (a) all users directly assigned the viewer relation and (b) all users who can view the document's parent folder.

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'document',
        relations: {
          viewer: {
            union: {
              child: [
                { this: {} },
                {
                  tupleToUserset: {
                    tupleset: {
                      object: '',
                      relation: 'parent_folder',
                    },
                    computedUserset: {
                      object: '',
                      relation: 'viewer',
                    },
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            viewer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }} skipVersion={true}
/>

In the authorization model above, `user:anne` is a `viewer` of `document:new-roadmap` if any one of the following relationship tuples sets exists:

- Anne is a viewer of the parent folder of the new-roadmap document
  <RelationshipTuplesViewer
    relationshipTuples={[
      {
        user: 'folder:planning',
        relation: 'parent_folder',
        object: 'document:new-roadmap',
        _description: 'planning folder is the parent folder of the new-roadmap document',
      },
      {
        user: 'user:anne',
        relation: 'viewer',
        object: 'folder:planning',
        _description: 'anne is a viewer of the planning folder',
      },
    ]}
  />
- Anne is a viewer of the new-roadmap document (direct relationship)
  <RelationshipTuplesViewer
    relationshipTuples={[
      {
        user: 'user:anne',
        relation: 'viewer',
        object: 'document:new-roadmap',
        _description: 'anne is a viewer of the new-roadmap document',
      },
    ]}
  />

Referencing relations on related objects defines transitive implied relationship. If User A is related to Object B as a viewer, and Object B is related to Object C as parent, then User A is related to Object C as viewer. This can indicate that viewers of a folders are viewers of all documents in that folder.

:::caution
<ProductName format={ProductNameFormat.LongForm}/> does not allow the referenced relation (the word after `from`, also called the tupleset) to reference another relation and does not allow non-concrete types (type bound public access (`<object_type>:*`) or usersets (`<object_type>#<relation>`)) in its type restrictions; adding them throws a validation error when calling `WriteAuthorizationModel`.
:::

For more examples, see [Modeling: Parent-Child Objects](https://github.com/openfga/openfga.dev/blob/main/./modeling/parent-child.mdx), [Advanced Modeling: Google Drive](https://github.com/openfga/openfga.dev/blob/main/./modeling/advanced/gdrive.mdx), [Advanced Modeling: GitHub](https://github.com/openfga/openfga.dev/blob/main/./modeling/advanced/github.mdx), and [Advanced Modeling: Entitlements](https://github.com/openfga/openfga.dev/blob/main/./modeling/advanced/entitlements.mdx).

##### The Union Operator

The **union operator** (`or` in the DSL, `union` in the JSON syntax) indicates that a <ProductConcept section="what-is-a-relationship" linkName="relationship" /> exists if the <ProductConcept section="what-is-a-user" linkName="user" /> is in any of the sets of users (`union`).

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'document',
        relations: {
          viewer: {
            // a user is related to the object as a viewer if:
            union: {
              // they are in any of
              child: [
                {
                  this: {}, // the userset of all users related to the object as "viewer"; indicating that a user can be assigned a direct `viewer` relation, i.e., not implied through another relation
                },
                {
                  computedUserset: {
                    relation: 'editor', // the userset of all users related to the object as "editor"; indicating that a user who is an editor is also implicitly a viewer
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            viewer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }} skipVersion={true}
/>

In the <ProductConcept section="what-is-a-type-definition" linkName="type definition" /> snippet above, `user:anne` is a `viewer` of `document:new-roadmap` if any of the following conditions are satisfied:

- there exists a <ProductConcept section="what-are-direct-and-implied-relationships" linkName="direct relationship" /> with _anne_ as _editor_ of _document:new-roadmap_
  <RelationshipTuplesViewer
    relationshipTuples={[
      {
        user: 'user:anne',
        relation: 'editor',
        object: 'document:new-roadmap',
      },
    ]}
  />
- _anne_ is a _viewer_ of _document:new-roadmap_
  <RelationshipTuplesViewer
    relationshipTuples={[
      {
        user: 'user:anne',
        relation: 'viewer',
        object: 'document:new-roadmap',
      },
    ]}
  />

:::info

The above <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" /> indicates that a user is related as a viewer if they are in any of the following:

- the userset of all users related to the object as "viewer", indicating that a user can be assigned a direct `viewer` relation
- the userset of all users related to the object as "editor", indicating that a user who is an editor is also implicitly a viewer

If `anne` is in at least one of those usersets, meaning `anne` is either an `editor` or a `viewer`, the <ProductConcept section="what-is-a-check-request" linkName="check" /> on `{"user": "user:anne", "relation": "viewer", "object": "document:new-roadmap"}` returns `{"allowed": true}`.

:::

For more examples, see [Modeling Building Blocks: Concentric Relationships](https://github.com/openfga/openfga.dev/blob/main/./modeling/building-blocks/concentric-relationships.mdx), [Modeling Roles and Permissions](https://github.com/openfga/openfga.dev/blob/main/./modeling/roles-and-permissions.mdx) and [Advanced Modeling: Modeling for IoT](https://github.com/openfga/openfga.dev/blob/main/./modeling/advanced/iot.mdx#03-updating-our-authorization-model-to-facilitate-future-changes).

##### The Intersection Operator

The **intersection operator** (`and` in the DSL, `intersection` in the JSON syntax) indicates that a <ProductConcept section="what-is-a-relationship" linkName="relationship" /> exists if the <ProductConcept section="what-is-a-user" linkName="user" /> is in all the sets of users.

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'document',
        relations: {
          viewer: {
            // a user is related to the object as a viewer if
            intersection: {
              // they are in all of
              child: [
                {
                  computedUserset: {
                    // the userset of all users related to the object as "authorized_user"
                    relation: 'authorized_user',
                  },
                },
                {
                  computedUserset: {
                    // the userset of all users related to the object as "editor"
                    relation: 'editor',
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            viewer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }} skipVersion={true}
/>

In the <ProductConcept section="what-is-a-type-definition" linkName="type definition" /> snippet above, `user:anne` is a `viewer` of `document:new-roadmap` if all of the following conditions are satisfied:

- _anne_ is an _editor_ of _document:new-roadmap_
  <RelationshipTuplesViewer
    relationshipTuples={[
      {
        user: 'user:anne',
        relation: 'editor',
        object: 'document:new-roadmap',
      },
    ]}
  />
  AND
- _anne_ is an _authorized_user_ of _document:new-roadmap_:
  <RelationshipTuplesViewer
    relationshipTuples={[
      {
        user: 'user:anne',
        relation: 'authorized_user',
        object: 'document:new-roadmap',
      },
    ]}
  />

:::info

The above <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" /> indicates that a user is related as a viewer if they are in all of the following:

- the userset of all users related to the object as `authorized_user`
- the userset of all users related to the object as `editor`

`anne` must be in the intersection of the usersets (meaning both an `editor` AND an `authorized_user`) for the <ProductConcept section="what-is-a-check-request" linkName="check" /> on `{"user": "user:anne", "relation": "viewer", "object": "document:new-roadmap"}` to return `{"allowed": true}`.

`anne` is not a `viewer` for `document:new-roadmap` if either of the following is true:

- `anne` is not an `editor` to `document:new-roadmap`: no relationship tuple of `{"user": "user:anne", "relation": "editor", "object": "document:new-roadmap"}`
- `anne` is not an `authorized_user` on the `document:new-roadmap`: no relationship tuple of `{"user": "user:anne", "relation": "authorized_user", "object": "document:new-roadmap"}`

:::

For more examples, see [Modeling with Multiple Restrictions](https://github.com/openfga/openfga.dev/blob/main/./modeling/multiple-restrictions.mdx).

##### The Exclusion Operator

The **exclusion operator** (`but not` in the DSL, `difference` in the JSON syntax) indicates that a <ProductConcept section="what-is-a-relationship" linkName="relationship" /> exists if the <ProductConcept section="what-is-a-user" linkName="user" /> is in the base userset but not in the excluded userset. This operator is particularly useful when modeling exclusion or block lists.

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'document',
        relations: {
          viewer: {
            // a user is related to the object as a viewer if they are in
            difference: {
              base: {
                this: {}, // the userset of all users related to the object as "viewer"
              },
              subtract: {
                computedUserset: {
                  relation: 'blocked', // but not in the userset of all users related to the object as "blocked"
                },
              },
            },
          },
        },
        metadata: {
          relations: {
            viewer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }} skipVersion={true}
/>

In the type definition snippet above, `user:anne` is a `viewer` of `document:new-roadmap` if and only if:

- `anne` has a direct relationship as `viewer` to `document:new-roadmap`

  <RelationshipTuplesViewer
    relationshipTuples={[
      {
        user: 'user:anne',
        relation: 'viewer',
        object: 'document:new-roadmap',
      },
    ]}
  />
  AND

- `anne` is not blocked from `document:new-roadmap` (i.e., the following relationship tuple must not exist):
  <RelationshipTuplesViewer
    relationshipTuples={[
      {
        user: 'user:anne',
        relation: 'blocked',
        object: 'document:new-roadmap',
      },
    ]}
  />

For more information, see [Modeling: Blocklists](https://github.com/openfga/openfga.dev/blob/main/./modeling/blocklists.mdx).

:::info

The <ProductConcept section="what-is-an-authorization-model" linkName="authorization model" /> above indicates that a user is related as a viewer if they are in:

- the userset of all users related to the object as `viewer`

but not in:

- the userset of all users related to the object as `blocked`

`anne` must be both a `viewer` and not `blocked` for the <ProductConcept section="what-is-a-check-request" linkName="check" /> on `{"user": "user:anne", "relation": "viewer", "object": "document:new-roadmap"}` to return `{"allowed": true}`.

`anne` is not a viewer for document:new-roadmap if either of the following is true:

- `anne` is **not** assigned direct relationship as viewer to document:new-roadmap: **no relationship tuple of** `{"user": "user:anne", "relation": "viewer", "object": "document:new-roadmap"}`
- `anne` is blocked on the document:new-roadmap `{"user": "user:anne", "relation": "blocked", "object": "document:new-roadmap"}`

:::
##### Grouping and nesting operators

You can define complex conditions by using parentheses to group and nest operators. Note that direct relationships can be included in an expression with parentheses.

<AuthzModelSnippetViewer
  configuration={
   {
  "schema_version": "1.1",
  "type_definitions": [
    {
      "type": "user",
      "relations": {},
      "metadata": null
    },
    {
      "type": "organization",
      "relations": {
        "member": {
          "this": {}
        }
      },
      "metadata": {
        "relations": {
          "member": {
            "directly_related_user_types": [
              {
                "type": "user"
              }
            ]
          }
        }
      }
    },
    {
      "type": "folder",
      "relations": {
        "organization": {
          "this": {}
        },
        "parent": {
          "this": {}
        },
        "viewer": {
          "intersection": {
            "child": [
              {
                "union": {
                  "child": [
                    {
                      "this": {}
                    },
                    {
                      "tupleToUserset": {
                        "computedUserset": {
                          "relation": "viewer"
                        },
                        "tupleset": {
                          "relation": "parent"
                        }
                      }
                    }
                  ]
                }
              },
              {
                "tupleToUserset": {
                  "computedUserset": {
                    "relation": "member"
                  },
                  "tupleset": {
                    "relation": "organization"
                  }
                }
              }
            ]
          }
        }
      },
      "metadata": {
        "relations": {
          "organization": {
            "directly_related_user_types": [
              {
                "type": "organization"
              }
            ]
          },
          "parent": {
            "directly_related_user_types": [
              {
                "type": "folder"
              }
            ]
          },
          "viewer": {
            "directly_related_user_types": [
              {
                "type": "user"
              }
            ]
          }
        }
      }
    }
  ]
}
  } skipVersion={true}
/>


##### Conditional relationships

<ProductName format={ProductNameFormat.ShortForm}/> supports conditional relationships, which are only considered if a specific condition is met. You can learn more about Conditional Relationships in the [Modeling: Conditional Relationships](https://github.com/openfga/openfga.dev/blob/main/./modeling/conditions.mdx) guide.

#### Equivalent Zanzibar Concepts

The JSON syntax accepted by the <ProductName format={ProductNameFormat.ShortForm}/> API closely mirrors the syntax represented in the Zanzibar paper. The major modifications are a slight flattening and conversion of keys from `snake_case` to `camelCase`.

| Zanzibar           | <ProductName format={ProductNameFormat.ShortForm}/> JSON | <ProductName format={ProductNameFormat.ShortForm}/> DSL |
| :----------------- | :------------------------------------------------------- | :------------------------------------------------------ |
| `this`             | `this`                                                   | [`[<type1>,<type2>]`](#direct-relationship-type-restrictions)                                                  |
| `union`            | `union`                                                  | `or`                                                    |
| `intersection`     | `intersection`                                           | `and`                                                   |
| `exclusion`        | `difference`                                             | `but not`                                               |
| `tuple_to_userset` | `tupleToUserset`                                         | `x from y`                                              |

The [Zanzibar paper](https://research.google/pubs/pub48190/) presents this example:

```
name: "doc"

relation { name: "owner" }

relation {
  name: "editor"
  userset_rewrite {
    union {
      child { _this {} }
      child { computed_userset { relation: "owner" } }
}}}

relation {
 name: "viewer"
 userset_rewrite {
  union {
    child { _this {} }
    child { computed_userset { relation: "editor" } }
    child { tuple_to_userset {
      tupleset { relation: "parent" }
      computed_userset {
        object: $TUPLE_USERSET_OBJECT  # parent folder
        relation: "viewer" }}}
}}}
```

In the <ProductName format={ProductNameFormat.ShortForm}/> DSL, it becomes:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'doc',
        relations: {
          owner: {
            this: {},
          },
          editor: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'owner',
                  },
                },
              ],
            },
          },
          viewer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'editor',
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      relation: 'parent',
                    },
                    computedUserset: {
                      relation: 'viewer',
                    },
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            owner: { directly_related_user_types: [{ type: 'user' }] },
            editor: { directly_related_user_types: [{ type: 'user' }] },
            viewer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

In the <ProductName format={ProductNameFormat.ShortForm}/> JSON, it becomes:

<AuthzModelSnippetViewer
  syntaxesToShow={[SyntaxFormat.Json]}
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'doc',
        relations: {
          owner: {
            this: {},
          },
          editor: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'owner',
                  },
                },
              ],
            },
          },
          viewer: {
            union: {
              child: [
                {
                  this: {},
                },
                {
                  computedUserset: {
                    relation: 'editor',
                  },
                },
                {
                  tupleToUserset: {
                    tupleset: {
                      relation: 'parent',
                    },
                    computedUserset: {
                      relation: 'viewer',
                    },
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            owner: { directly_related_user_types: [{ type: 'user' }] },
            editor: { directly_related_user_types: [{ type: 'user' }] },
            viewer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

The following snippet:

<AuthzModelSnippetViewer
  configuration={{
    schema_version: '1.1',
    type_definitions: [
      {
        type: 'doc',
        relations: {
          viewer: {
            union: {
              child: [
                {
                  // a user can be assigned a direct `viewer` relation, i.e., not implied through another relation
                  this: {},
                },
                {
                  // a user that is an editor is also implicitly a viewer
                  computedUserset: {
                    relation: 'editor',
                  },
                },
                {
                  // a user that is a viewer on any of the object's parents is also implicitly a viewer on the object
                  tupleToUserset: {
                    tupleset: {
                      relation: 'parent',
                    },
                    computedUserset: {
                      relation: 'viewer',
                    },
                  },
                },
              ],
            },
          },
        },
        metadata: {
          relations: {
            viewer: { directly_related_user_types: [{ type: 'user' }] },
          },
        },
      },
    ],
  }}
/>

Results in the following outcome:

- The users with a viewer relationship to a certain doc are any of:
  - the set of users who are <ProductConcept section="what-are-direct-and-implied-relationships" linkName="directly related" /> with this doc as `viewer`
  - the set of users who are related to this doc as `editor`
  - the set of users who are related to any object OBJ_1 as `viewer`, where object OBJ_1 is any object related to this doc as `parent` (e.g. viewers of this doc's parent folder, where the parent folder is OBJ_1)

Learn more about Zanzibar at the [Zanzibar Academy](https://zanzibar.academy).

#### Related Sections

<RelatedSection
  description="Check the following sections for more on how to use the configuration language in modeling authorization."
  relatedLinks={[
    {
      title: '{ProductName} Concepts',
      description: 'Learn about the {ProductName} Concepts.',
      link: './concepts',
      id: './concepts',
    },
    {
      title: 'Modeling: Getting Started',
      description: 'Learn about how to get started with modeling your permission system in {ProductName}.',
      link: './modeling/getting-started',
      id: './modeling/getting-started',
    },
    {
      title: 'Direct Access',
      description: 'Learn about modeling user access to an object.',
      link: './modeling/direct-access',
      id: './modeling/direct-access',
    },
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/configuration-language.mdx -->


<!-- Source: openfga/openfga.dev/docs/content/intro.mdx -->

---
title: Introduction to FGA
description: Introduction to FGA
sidebar_position: 1
slug: /fga
---

import { ProductName,  ProductNameFormat, RelatedSection } from '@components/Docs';

### Introduction to <ProductName format={ProductNameFormat.LongForm}/>

<ProductName format={ProductNameFormat.ShortForm}/> is a scalable open source authorization system for developers that allows implementing authorization for any kind of application and smoothly evolve as complexity increases over time. It is owned by the [Cloud Native Computing Foundation](https://cncf.io).

Inspired by [Google’s Zanzibar](https://zanzibar.academy), Google’s internal authorization system, <ProductName format={ProductNameFormat.ShortForm}/> relies on Relationship-Based Access Control, which allows developers to easily implement Role-Based Access Control and provides additional capabilities to implement Attribute-Based Access Control. You can learn more about different authorization concepts [here](https://github.com/openfga/openfga.dev/blob/main/./authorization-concepts.mdx).

#### Benefits
<ProductName format={ProductNameFormat.ShortForm}/> provides developers the following benefits:


- Move authorization logic outside of application code, making it easier to write, change and audit.
- Increase velocity by standardizing on a single authorization solution.
- Centralize authorization decisions and audit logs making it simpler to comply with security and compliance requirements.
- Help their products to move faster because it is simpler to evolve authorization policies.


#### Features

<ProductName format={ProductNameFormat.ShortForm}/> helps developers achieve those benefits with features as:


- Support for multiple [stores](https://github.com/openfga/openfga.dev/blob/main/./concepts.mdx#what-is-a-store) that allow authorization management in different environments (prod/testing/dev) and use cases (internal apps, external apps, infrastructure).
- Support for some ABAC scenarios with [Contextual Tuples](https://github.com/openfga/openfga.dev/blob/main/./modeling/token-claims-contextual-tuples.mdx) and [Conditional Relationship Tuples](https://github.com/openfga/openfga.dev/blob/main/./modeling/conditions.mdx).
- SDKs for [Java](https://github.com/openfga/java-sdk), [.NET](https://github.com/openfga/dotnet-sdk), [Javascript](https://github.com/openfga/js-sdk), [Go](https://github.com/openfga/go-sdk), and [Python](https://github.com/openfga/python-sdk).
- [HTTP](https://docs.fga.dev/api/service) and [gRPC](https://buf.build/openfga/api) APIs.
- Support for being run as a library, from with a Go based service.
- Support for using Postgres, MySQL or SQLite as the production datastore, as well as an in-memory datastore for non-production usage.
- [A Command Line Interface tool](https://github.com/openfga/openfga.dev/blob/main/./getting-started/cli.mdx) for managing <ProductName format={ProductNameFormat.ShortForm}/> stores, test models, import/export models, and data.
- Github Actions for [testing](https://github.com/marketplace/actions/openfga-model-testing-action) and [deploying](https://github.com/marketplace/actions/openfga-model-deploy-action) models.
- A [Visual Studio Code Extension](https://marketplace.visualstudio.com/items?itemName=openfga.openfga-vscode) with syntax highlighting and validation of FGA models and tests.
- [Helm Charts](https://github.com/openfga/helm-charts) to easily deploy to Kubernetes.
- [OpenTelemetry](https://openfga.dev/docs/getting-started/setup-openfga/configure-openfga#telemetry) support to integrate it with your monitoring infrastructure.


#### Related Sections

<RelatedSection
  description="Check the following sections to learn more about {ProductName}."
  relatedLinks={[
    {
      title: 'Authorization Concepts',
      description: 'Learn about Authorization.',
      link: './authorization-concepts',
      id: './authorization-concepts',
    },
    {
      title: 'Product Concepts',
      description: 'Learn about {ProductName}.',
      link: './concepts',
      id: './concepts',
    },
    {
      title: 'Modeling: Getting Started',
      description: 'Learn about how to get started with modeling your permission system in {ProductName}.',
      link: './modeling/getting-started',
      id: './modeling/getting-started',
    }
  ]}
/>


<!-- End of openfga/openfga.dev/docs/content/intro.mdx -->
