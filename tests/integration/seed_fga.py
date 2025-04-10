import asyncio
import os

# Core client and configuration
from openfga_sdk import OpenFgaClient

# Use ClientConfiguration from the client submodule
from openfga_sdk.client.configuration import ClientConfiguration

# Import specific Client models for write operation
from openfga_sdk.client.models import ClientTuple, ClientWriteRequest
from openfga_sdk.exceptions import ApiException

# Models used for requests and responses
from openfga_sdk.models.create_store_request import CreateStoreRequest
from openfga_sdk.models.metadata import Metadata
from openfga_sdk.models.relation_metadata import RelationMetadata

# Use RelationReference instead of DirectlyRelatedUserType
from openfga_sdk.models.relation_reference import RelationReference
from openfga_sdk.models.type_definition import TypeDefinition
from openfga_sdk.models.userset import Userset
from openfga_sdk.models.write_authorization_model_request import WriteAuthorizationModelRequest


async def main():
    """Seeds the OpenFGA instance with mock data."""
    fga_api_scheme = os.getenv("FGA_API_SCHEME", "http")
    fga_api_host = os.getenv("FGA_API_HOST", "127.0.0.1:8080")
    fga_store_name = "test_store"

    config = ClientConfiguration(
        api_scheme=fga_api_scheme,
        api_host=fga_api_host,
    )

    async with OpenFgaClient(configuration=config) as fga_client:
        store_id = None
        # 1. Find or Create a Store
        try:
            stores_resp = await fga_client.list_stores()
            found_store = next((s for s in stores_resp.stores if s.name == fga_store_name), None)  # type: ignore

            if found_store:
                store_id = found_store.id
            else:
                create_store_req = CreateStoreRequest(name=fga_store_name)
                create_resp = await fga_client.create_store(body=create_store_req)
                store_id = create_resp.id  # type: ignore

            fga_client.set_store_id(store_id)

        except ApiException as e:
            print(f"FATAL: OpenFGA API Exception during store handling: {e.status} - {e.body}")
            return
        except AttributeError as e:
            print(f"FATAL: Error accessing store data, possibly SDK structure difference: {e}")
            return
        except Exception as e:
            print(f"FATAL: Unexpected error during store handling: {e}")
            return

        # 2. Define and Write Authorization Model
        model_request = WriteAuthorizationModelRequest(
            schema_version="1.1",
            type_definitions=[
                TypeDefinition(type="user", relations={}),
                TypeDefinition(
                    type="document",
                    relations={  # type: ignore[call-arg]
                        "viewer": Userset(this={}),
                        "owner": Userset(this={}),
                    },
                    metadata=Metadata(
                        relations={
                            "viewer": RelationMetadata(
                                # Use directly_related_user_types
                                directly_related_user_types=[RelationReference(type="user")]
                            ),
                            "owner": RelationMetadata(
                                # Use directly_related_user_types
                                directly_related_user_types=[RelationReference(type="user")]
                            ),
                        }
                    ),
                ),
            ],
        )

        auth_model_id = None
        try:
            model_resp = await fga_client.write_authorization_model(body=model_request)
            auth_model_id = model_resp.authorization_model_id  # type: ignore
        except ApiException as e:
            if e.status == 400 and e.body and "authorization model already exists" in str(e.body).lower():
                try:
                    models_resp = await fga_client.read_authorization_models()
                    if models_resp.authorization_models:  # type: ignore
                        auth_model_id = models_resp.authorization_models[0].id  # type: ignore
                except ApiException as read_e:
                    print(f"Warning: Failed to read existing authorization models: {read_e.status} - {read_e.body}")
            else:
                print(f"FATAL: OpenFGA API Exception writing authorization model: {e.status} - {e.body}")
                return
        except Exception as e:
            print(f"FATAL: Unexpected error writing authorization model: {e}")
            return

        # Set the authorization model ID for subsequent operations if available
        if auth_model_id:
            fga_client.set_authorization_model_id(auth_model_id)

        # 3. Write Tuples (Relationships)
        tuples_to_write = [
            ClientTuple(user="user:anne", relation="owner", object="document:report1"),
            ClientTuple(user="user:bob", relation="viewer", object="document:report1"),
            ClientTuple(user="user:anne", relation="owner", object="document:report2"),
            ClientTuple(user="user:charlie", relation="viewer", object="document:report2"),
        ]

        write_req_body = ClientWriteRequest(
            writes=tuples_to_write,
        )

        try:
            await fga_client.write(body=write_req_body)

        except ApiException as e:
            print(f"FATAL: OpenFGA API Exception writing tuples: {e.status} - {e.body}")
        except Exception as e:
            print(f"FATAL: Unexpected error writing tuples: {e}")

    print("Seeding complete.")


if __name__ == "__main__":
    wait_time = 5

    async def run_with_delay():
        await asyncio.sleep(wait_time)
        await main()

    asyncio.run(run_with_delay())
